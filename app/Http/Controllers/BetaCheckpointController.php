<?php

namespace App\Http\Controllers;

use App\Models\StudyMaterial;
use App\Models\StudyQuestion;
use App\Models\User;
use App\Models\UserBetaCheckpoint;
use App\Models\UserStudyMaterial;
use App\Services\StudyMaterialService;
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
 * Desafio da etapa no mapa beta: a fase paralela ao fim de cada etapa. Reúne os resumos e as
 * questões (materiais de estudo escritos por um agente de IA e publicados pelo admin) de todos
 * os dispositivos da trilha até ali. Só para assinantes, não consome vida e fica fora do
 * avanço das fases. O gabarito nunca vai para o navegador antes da resposta.
 */
class BetaCheckpointController extends BetaPlayController
{
    /** XP da primeira conclusão de um material: base + um tanto por acerto. Refazer não dá XP. */
    const MATERIAL_XP_BASE = 10;

    const MATERIAL_XP_PER_CORRECT = 15;

    public function show(string $checkpoint): Response|RedirectResponse
    {
        $user = Auth::user();
        $found = $this->findCheckpoint($user, $checkpoint);

        abort_if(! $found, 404);

        if ($found['premium_required'] || ! in_array($found['state'], ['available', 'complete'], true)) {
            return redirect()->route('beta.play.map');
        }

        $done = array_flip($this->doneIds($user, $found));
        $service = app(StudyMaterialService::class);

        return Inertia::render('BetaPlay/Checkpoint', [
            'checkpoint' => [
                'key' => $found['key'],
                'number' => $found['number'],
                'total' => $found['total'],
                'completed' => $found['completed'],
            ],
            // Na ordem da trilha; a tela começa pelo primeiro ainda não feito nesta etapa
            'materials' => $this->materials($found)->map(fn (StudyMaterial $material) => [
                'uuid' => $material->uuid,
                'title' => $material->title,
                'legislation_title' => $material->legislation->title,
                'summary_html' => $service->renderSummary($material->summary),
                // Sem gabarito, comentário nem fundamento: chegam depois de cada resposta
                'questions' => $material->questions->map(fn (StudyQuestion $question) => [
                    'position' => $question->position,
                    'board' => $question->board,
                    'type' => $question->type,
                    'statement' => $question->statement,
                    'options' => $question->options,
                ])->values(),
                'done' => isset($done[$material->id]),
            ])->values(),
        ]);
    }

    /** Corrige uma questão e devolve o gabarito comentado. Não grava nada. */
    public function answer(Request $request, string $checkpoint): JsonResponse
    {
        $validated = $request->validate([
            'material' => ['required', 'uuid'],
            'position' => ['required', 'integer'],
            'answer' => ['required', 'string', 'max:10'],
        ]);

        $material = $this->authorizeMaterial(Auth::user(), $checkpoint, $validated['material']);

        $question = $material->questions->firstWhere('position', (int) $validated['position']);
        abort_if(! $question, 422, 'Questão inexistente.');

        return response()->json([
            'is_correct' => $this->isCorrect($question, $validated['answer']),
            'correct_answer' => $question->correct_answer,
            'explanation' => $question->explanation,
            'legal_basis' => $question->legal_basis,
        ]);
    }

    /**
     * Fim da fase: recebe todos os materiais jogados de uma vez, recalcula os acertos no
     * servidor, marca os materiais como feitos nesta etapa e só então dá o XP, apenas pelos
     * materiais concluídos pela primeira vez. Sair no meio não grava nada.
     */
    public function finish(Request $request, string $checkpoint): JsonResponse
    {
        $validated = $request->validate([
            'results' => ['required', 'array', 'min:1'],
            'results.*.material' => ['required', 'uuid', 'distinct'],
            'results.*.answers' => ['present', 'array'],
            'results.*.answers.*' => ['required', 'string', 'max:10'],
        ]);

        $user = Auth::user();
        $found = $this->authorizedCheckpoint($user, $checkpoint);

        $materials = StudyMaterial::whereIn('uuid', array_column($validated['results'], 'material'))
            ->with('questions')
            ->get()
            ->keyBy('uuid');

        // Corrige tudo antes de gravar qualquer coisa
        $graded = [];
        foreach ($validated['results'] as $result) {
            $material = $materials->get($result['material']);
            abort_if(! $material || ! in_array($material->id, $found['material_ids'], true), 404);

            $answers = [];
            $correct = 0;
            foreach ($material->questions as $question) {
                $answer = $result['answers'][$question->position] ?? null;
                abort_if($answer === null, 422, 'Responda todas as questões.');

                $answers[$question->position] = $answer;
                $correct += $this->isCorrect($question, $answer) ? 1 : 0;
            }

            $graded[] = ['material' => $material, 'answers' => $answers, 'correct' => $correct];
        }

        // XP por lei, para a conquista de cada disciplina
        $xpByLegislation = DB::transaction(function () use ($user, $graded, $found) {
            $progress = UserBetaCheckpoint::where('user_id', $user->id)
                ->where('legislation_segment_id', $found['anchor_block_id'])
                ->lockForUpdate()
                ->first() ?? new UserBetaCheckpoint([
                    'user_id' => $user->id,
                    'legislation_segment_id' => $found['anchor_block_id'],
                    'done_material_ids' => [],
                ]);
            $progress->done_material_ids = array_values(array_unique([
                ...$progress->doneIds(),
                ...array_map(fn ($item) => $item['material']->id, $graded),
            ]));
            $progress->save();

            $xpByLegislation = [];
            foreach ($graded as ['material' => $material, 'answers' => $answers, 'correct' => $correct]) {
                $record = UserStudyMaterial::where('user_id', $user->id)
                    ->where('study_material_id', $material->id)
                    ->lockForUpdate()
                    ->first() ?? new UserStudyMaterial(['user_id' => $user->id, 'study_material_id' => $material->id]);

                $first = $record->completed_at === null;

                $record->fill([
                    'answers' => $answers,
                    'correct_count' => $correct,
                    'completed_at' => $record->completed_at ?? now(),
                ])->save();

                if ($first) {
                    $xp = $user->boostedXp(self::MATERIAL_XP_BASE + self::MATERIAL_XP_PER_CORRECT * $correct);
                    $user->addXp($xp, 'study_material', $material->id, $material->legislation_id);
                    $xpByLegislation[$material->legislation_id] = ($xpByLegislation[$material->legislation_id] ?? 0) + $xp;
                }
            }

            return $xpByLegislation;
        });

        $levelUp = null;
        foreach ($xpByLegislation as $legislationId => $xp) {
            $levelUp ??= app(XpService::class)->detectDisciplineLevelUp($user, $legislationId, $xp);
        }

        $doneNow = count(array_intersect($found['material_ids'], $this->doneIds($user, $found)));

        return response()->json([
            'correct_count' => array_sum(array_column($graded, 'correct')),
            'total' => array_sum(array_map(fn ($item) => $item['material']->questions->count(), $graded)),
            'xp_gained' => array_sum($xpByLegislation),
            'discipline_level_up' => $levelUp,
            'checkpoint' => ['completed' => $doneNow, 'total' => $found['total']],
            'user' => ['xp' => $user->xp],
        ]);
    }

    // ==================== PRIVATE METHODS ====================

    /** O desafio da etapa cujo último bloco tem esse uuid, na trilha do usuário. */
    private function findCheckpoint(User $user, string $key): ?array
    {
        $sections = $this->buildSections($this->betaPhases($user));

        foreach ($this->checkpointsFor($user, $sections) as $si => $checkpoint) {
            if ($checkpoint['key'] !== null && $checkpoint['key'] === $key) {
                return $checkpoint + ['number' => $sections[$si]['number']];
            }
        }

        return null;
    }

    /** As checagens de show() para as rotas JSON. */
    private function authorizedCheckpoint(User $user, string $key): array
    {
        $found = $this->findCheckpoint($user, $key);

        abort_if(! $found, 404);
        abort_if($found['premium_required'], 403, 'O desafio da etapa é exclusivo para assinantes.');
        abort_if(! in_array($found['state'], ['available', 'complete'], true), 403, 'Conclua as fases desta etapa para liberar o desafio.');

        return $found;
    }

    /** O material pedido, que precisa estar no desafio desta etapa. */
    private function authorizeMaterial(User $user, string $key, string $materialUuid): StudyMaterial
    {
        $found = $this->authorizedCheckpoint($user, $key);

        $material = StudyMaterial::where('uuid', $materialUuid)->with('questions')->first();
        abort_if(! $material || ! in_array($material->id, $found['material_ids'], true), 404);

        return $material;
    }

    /** @return Collection<int, StudyMaterial> */
    private function materials(array $checkpoint): Collection
    {
        $position = array_flip($checkpoint['material_ids']);

        return StudyMaterial::whereIn('id', $checkpoint['material_ids'])
            ->with(['questions', 'legislation:id,title'])
            ->get()
            ->sortBy(fn (StudyMaterial $material) => $position[$material->id])
            ->values();
    }

    /** @return array<int, int> */
    private function doneIds(User $user, array $checkpoint): array
    {
        return UserBetaCheckpoint::where('user_id', $user->id)
            ->where('legislation_segment_id', $checkpoint['anchor_block_id'])
            ->first()?->doneIds() ?? [];
    }

    private function isCorrect(StudyQuestion $question, string $answer): bool
    {
        return Str::lower(trim($answer)) === Str::lower($question->correct_answer);
    }
}
