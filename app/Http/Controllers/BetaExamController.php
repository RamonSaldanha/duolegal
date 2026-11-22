<?php

namespace App\Http\Controllers;

use App\Models\LegislationSegment;
use App\Models\StudyQuestion;
use App\Models\User;
use App\Models\UserBetaExam;
use App\Services\EnergyService;
use App\Services\XpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dia da prova no mapa beta: a fase paralela ao fim de cada etapa. É uma prova com tempo, só
 * com as questões que a pessoa já viu nos desafios dos quadrados da etapa, em ordem sorteada.
 * O tempo é a soma do tempo de cada questão e quem manda nele é o servidor: a tentativa guarda
 * a hora em que acaba e resposta fora do prazo não entra. Errar não custa nada. O XP é a soma
 * dos acertos, creditado quando a prova é entregue (ou o tempo acaba); cada questão dá XP uma
 * vez só, no primeiro acerto, então refazer a prova rende só o que ainda não foi acertado.
 * Livre para qualquer usuário, não gasta energia e rende energia na entrega (por acerto novo,
 * combo e barra completada com nota alta; ver EnergyService). Fica fora do avanço da trilha.
 * O gabarito só vai para o navegador depois de cada resposta, e o comentário só no fim.
 */
class BetaExamController extends BetaPlayController
{
    /** Folga, em segundos, para a resposta dada no último instante chegar ao servidor. */
    const GRACE_SECONDS = 3;

    public function show(string $exam): Response|RedirectResponse
    {
        $payload = $this->examPayload(Auth::user(), $exam);

        return $payload === 'unavailable'
            ? redirect()->route('play.map')
            : Inertia::render('BetaPlay/Exam', $payload);
    }

    /**
     * Os dados do Dia da prova, ou 'unavailable' se ele ainda não abre (404 se não existe na
     * trilha do usuário). Uma tentativa esquecida aberta com o prazo vencido é entregue antes.
     *
     * @return array<string, mixed>|'unavailable'
     */
    protected function examPayload(User $user, string $exam): array|string
    {
        $found = $this->findExam($user, $exam);

        abort_if(! $found, 404);

        if (! in_array($found['state'], ['available', 'complete'], true)) {
            return 'unavailable';
        }

        $questions = $this->questions($found);

        // Tentativa esquecida aberta com o prazo vencido: entrega antes de mostrar a tela
        $row = DB::transaction(function () use ($user, $found, $questions) {
            $row = $this->row($user, $found, lock: true);
            if ($row?->isOpen() && ! $row->isRunning()) {
                $this->settle($user, $row, $questions);
            }

            return $row;
        });

        return [
            'exam' => [
                'key' => $found['key'],
                'section_number' => $found['section_number'],
                'legislation_title' => $found['legislation_title'],
                'questions' => $found['questions'],
                'seconds' => $found['seconds'],
                'xp_per_correct' => $found['xp_per_correct'],
                'retake_energy' => $found['retake_energy'],
                // Da linha, que pode ter acabado de ser entregue aqui em cima
                'best_correct' => min($found['questions'], (int) ($row?->best_correct ?? 0)),
                'attempts' => (int) ($row?->attempts ?? 0),
            ],
            // Prova em andamento: a tela volta direto nela, com o tempo que resta
            'attempt' => $row?->isRunning() ? $this->attemptPayload($user, $row, $questions) : null,
        ];
    }

    /**
     * Começa uma tentativa (ou devolve a que já está em andamento): sorteia a ordem e marca o
     * prazo. Refazer (da segunda vez em diante) custa energia; sem ela, 403 `no_energy`.
     */
    public function start(string $exam): JsonResponse
    {
        $user = Auth::user();
        $found = $this->authorizedExam($user, $exam);
        $questions = $this->questions($found);

        abort_if($questions->isEmpty(), 422, 'Esta prova ainda não tem questões.');

        UserBetaExam::firstOrCreate(['user_id' => $user->id, 'legislation_segment_id' => $found['anchor_block_id']]);

        $row = DB::transaction(function () use ($user, $found, $questions) {
            $row = $this->row($user, $found, lock: true);

            if ($row->isRunning()) {
                return $row;
            }
            if ($row->isOpen()) {
                $this->settle($user, $row, $questions);
            }

            if ($row->attempts > 0 && ! app(EnergyService::class)->examRetake($user)) {
                return null;
            }

            $row->update([
                'question_ids' => $questions->keys()->shuffle()->values()->all(),
                'answers' => null,
                'started_at' => now(),
                'deadline_at' => now()->addSeconds($questions->sum(fn (StudyQuestion $question) => $this->secondsFor($question))),
                'finished_at' => null,
            ]);

            return $row;
        });

        if (! $row) {
            return response()->json(['message' => 'Você está sem energia para refazer a prova.', 'code' => 'no_energy'], 403);
        }

        return response()->json($this->attemptPayload($user, $row, $questions));
    }

    /**
     * Corrige uma questão da tentativa e devolve o gabarito; vale a primeira resposta dada.
     * Fora do prazo a resposta não entra e a prova é entregue. Com a última questão respondida
     * a prova também é entregue.
     */
    public function answer(Request $request, string $exam): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'uuid'],
            'answer' => ['required', 'string', 'max:10'],
        ]);

        $user = Auth::user();
        $found = $this->authorizedExam($user, $exam);
        $questions = $this->questions($found);

        return DB::transaction(function () use ($user, $found, $questions, $validated) {
            $row = $this->row($user, $found, lock: true);

            abort_if(! $row?->isOpen(), 409, 'Não há prova em andamento.');

            if (now()->gt($row->deadline_at->copy()->addSeconds(self::GRACE_SECONDS))) {
                $levelUp = $this->settle($user, $row, $questions);

                return response()->json([
                    'expired' => true,
                    'result' => $this->result($user, $row, $questions, $levelUp),
                ], 409);
            }

            $question = $questions->firstWhere('uuid', $validated['question']);
            abort_if(! $question || ! in_array($question->id, $row->questionIds(), true), 404);

            $answers = $row->givenAnswers();
            $answers[$question->id] ??= [
                'answer' => $validated['answer'],
                'correct' => $this->isCorrect($question, $validated['answer']),
            ];
            $row->update(['answers' => $answers]);

            $finished = ! array_diff($row->questionIds(), array_keys($answers));
            $levelUp = $finished ? $this->settle($user, $row, $questions) : null;

            return response()->json([
                'is_correct' => $answers[$question->id]['correct'],
                'correct_answer' => $question->correct_answer,
                // XP e energia somados na prova até aqui; só são creditados na entrega
                'xp' => $finished ? (int) $row->last_xp : $this->pendingXp($user, $row),
                'energy' => $finished ? (int) $row->last_energy : $this->pendingEnergy($user, $row),
                'finished' => $finished,
                'result' => $finished ? $this->result($user, $row, $questions, $levelUp) : null,
            ]);
        });
    }

    /** Entrega a prova: o tempo acabou ou a pessoa decidiu parar. Credita o XP dos acertos. */
    public function finish(string $exam): JsonResponse
    {
        $user = Auth::user();
        $found = $this->authorizedExam($user, $exam);
        $questions = $this->questions($found);

        return DB::transaction(function () use ($user, $found, $questions) {
            $row = $this->row($user, $found, lock: true);

            abort_if(! $row || $row->started_at === null, 409, 'Não há prova em andamento.');

            // Se já foi entregue (pela última resposta, por exemplo), só devolve o resultado
            $levelUp = $row->isOpen() ? $this->settle($user, $row, $questions) : null;

            return response()->json($this->result($user, $row, $questions, $levelUp));
        });
    }

    // ==================== PRIVATE METHODS ====================

    /** O Dia da prova da etapa cujo último bloco tem esse uuid, na trilha do usuário. */
    private function findExam(User $user, string $key): ?array
    {
        foreach ($this->trail($user)['sections'] as $section) {
            if ($section['exam']['key'] !== null && $section['exam']['key'] === $key) {
                return $section['exam'] + [
                    'section_number' => $section['number'],
                    'legislation_title' => $section['legislation_title'],
                ];
            }
        }

        return null;
    }

    /** As checagens de show() para as rotas JSON. */
    private function authorizedExam(User $user, string $key): array
    {
        $found = $this->findExam($user, $key);

        abort_if(! $found, 404);
        abort_if(! in_array($found['state'], ['available', 'complete'], true), 403, 'Conclua os quadrados desta etapa para liberar o Dia da prova.');

        return $found;
    }

    /**
     * As questões da prova: as dos materiais publicados e atuais da etapa, por id.
     *
     * @return Collection<int, StudyQuestion>
     */
    private function questions(array $exam): Collection
    {
        return StudyQuestion::whereIn('study_material_id', $exam['material_ids'])
            ->orderBy('study_material_id')
            ->orderBy('position')
            ->get()
            ->keyBy('id');
    }

    private function row(User $user, array $exam, bool $lock = false): ?UserBetaExam
    {
        return UserBetaExam::where('user_id', $user->id)
            ->where('legislation_segment_id', $exam['anchor_block_id'])
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    private function secondsFor(StudyQuestion $question): int
    {
        return $question->type === StudyQuestion::TYPE_TRUE_FALSE
            ? self::EXAM_SECONDS_TRUE_FALSE
            : self::EXAM_SECONDS_MULTIPLE_CHOICE;
    }

    /**
     * Ids das questões acertadas na tentativa que ainda não deram XP.
     *
     * @return array<int, int>
     */
    private function newlyCorrect(UserBetaExam $row): array
    {
        $correct = array_keys(array_filter($row->givenAnswers(), fn (array $given) => $given['correct']));

        return array_values(array_diff(array_map('intval', $correct), $row->rewardedIds()));
    }

    /** XP que a tentativa já somou e ainda não foi creditado. */
    private function pendingXp(User $user, UserBetaExam $row): int
    {
        $new = count($this->newlyCorrect($row));

        return $new > 0 ? $user->boostedXp($new * self::EXAM_XP_PER_CORRECT) : 0;
    }

    /** Energia que a tentativa já somou e ainda não foi creditada (quem assina não ganha). */
    private function pendingEnergy(User $user, UserBetaExam $row): int
    {
        $energy = app(EnergyService::class);

        return $energy->infinite($user)
            ? 0
            : $energy->examGain($row->givenAnswers(), count($this->newlyCorrect($row)), (int) $row->attempts === 0);
    }

    /**
     * Entrega a tentativa aberta: credita o XP dos acertos que ainda não tinham dado XP, a
     * energia (com a barra completada se a primeira entrega teve nota alta) e guarda o melhor
     * resultado. Chamar dentro de uma transação, com a linha travada.
     *
     * @return array<string, mixed>|null a estrela ganha na disciplina com esse XP, se houve
     */
    private function settle(User $user, UserBetaExam $row, Collection $questions): ?array
    {
        $new = $this->newlyCorrect($row);
        $xp = $this->pendingXp($user, $row);
        $legislationId = LegislationSegment::whereKey($row->legislation_segment_id)->value('legislation_id');

        if ($xp > 0) {
            $user->addXp($xp, 'beta_exam', $row->legislation_segment_id, $legislationId);
        }

        $correct = count(array_filter($row->givenAnswers(), fn (array $given) => $given['correct']));
        $total = count($row->questionIds());
        $first = (int) $row->attempts === 0;
        $energy = app(EnergyService::class)->creditExam(
            $user,
            $this->pendingEnergy($user, $row),
            $first && $total > 0 && $correct / $total >= (float) config('energy.exam.fill_at'),
        );

        $row->update([
            'finished_at' => now(),
            'rewarded_question_ids' => [...$row->rewardedIds(), ...$new],
            'best_correct' => max((int) $row->best_correct, $correct),
            'attempts' => $row->attempts + 1,
            'last_xp' => $xp,
            'last_energy' => max(0, $energy),
        ]);

        return $xp > 0 ? app(XpService::class)->detectDisciplineLevelUp($user, $legislationId, $xp) : null;
    }

    /** As questões da tentativa, na ordem sorteada (as que ainda existem). */
    private function attemptQuestions(UserBetaExam $row, Collection $questions): Collection
    {
        return collect($row->questionIds())->map(fn (int $id) => $questions->get($id))->filter()->values();
    }

    /** A tentativa em andamento para a tela: questões sem gabarito, respostas já dadas e o tempo que resta. */
    private function attemptPayload(User $user, UserBetaExam $row, Collection $questions): array
    {
        $answers = $row->givenAnswers();
        $ordered = $this->attemptQuestions($row, $questions);

        return [
            'seconds_left' => max(0, (int) now()->diffInSeconds($row->deadline_at, false)),
            'seconds_total' => (int) $row->started_at->diffInSeconds($row->deadline_at),
            'xp' => $this->pendingXp($user, $row),
            'energy' => $this->pendingEnergy($user, $row),
            'questions' => $ordered->map(fn (StudyQuestion $question) => [
                'uuid' => $question->uuid,
                'board' => $question->board,
                'type' => $question->type,
                'statement' => $question->statement,
                'options' => $question->options,
            ])->all(),
            // Só das questões já respondidas
            'answers' => $ordered
                ->filter(fn (StudyQuestion $question) => isset($answers[$question->id]))
                ->mapWithKeys(fn (StudyQuestion $question) => [$question->uuid => [
                    'answer' => $answers[$question->id]['answer'],
                    'is_correct' => $answers[$question->id]['correct'],
                    'correct_answer' => $question->correct_answer,
                ]])
                ->all(),
        ];
    }

    /** O resultado da tentativa entregue, com o gabarito comentado do que foi respondido. */
    private function result(User $user, UserBetaExam $row, Collection $questions, ?array $levelUp): array
    {
        $answers = $row->givenAnswers();
        $answered = $this->attemptQuestions($row, $questions)->filter(fn (StudyQuestion $question) => isset($answers[$question->id]));

        return [
            'correct' => $answered->filter(fn (StudyQuestion $question) => $answers[$question->id]['correct'])->count(),
            'answered' => $answered->count(),
            'total' => count($row->questionIds()),
            'xp_gained' => (int) $row->last_xp,
            'energy_gained' => (int) $row->last_energy,
            'best_correct' => (int) $row->best_correct,
            'discipline_level_up' => $levelUp,
            'user' => ['xp' => $user->xp, 'energy' => app(EnergyService::class)->payload($user)],
            'review' => $answered->map(fn (StudyQuestion $question) => [
                'uuid' => $question->uuid,
                'board' => $question->board,
                'type' => $question->type,
                'statement' => $question->statement,
                'options' => $question->options,
                'answer' => $answers[$question->id]['answer'],
                'is_correct' => $answers[$question->id]['correct'],
                'correct_answer' => $question->correct_answer,
                'explanation' => $question->explanation,
                'legal_basis' => $question->legal_basis,
            ])->values()->all(),
        ];
    }

    private function isCorrect(StudyQuestion $question, string $answer): bool
    {
        return Str::lower(trim($answer)) === Str::lower($question->correct_answer);
    }
}
