<?php

namespace App\Http\Controllers;

use App\Models\StudyMaterial;
use App\Models\StudyQuestion;
use App\Models\User;
use App\Models\UserBetaCheckpoint;
use App\Models\UserStudyMaterial;
use App\Services\EnergyService;
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
 * Desafio do quadrado no mapa beta: o último passo do quadrado que tem material de estudo
 * (resumo e questões escritos por um agente de IA e publicados pelo admin) sobre os
 * dispositivos dele. Abre quando as fases do quadrado estão feitas e há energia; cada questão
 * nova gasta energia e acertos seguidos dão combo (EnergyService). O quadrado seguinte só abre
 * depois dele. Cada passo (resumo lido, questão respondida) é
 * gravado na hora e a pessoa volta de onde parou. O gabarito nunca vai para o navegador antes
 * da resposta.
 */
class BetaCheckpointController extends BetaPlayController
{
    /** XP da primeira conclusão de um material: base + um tanto por acerto. Refazer não dá XP. */
    const MATERIAL_XP_BASE = 10;

    const MATERIAL_XP_PER_CORRECT = 15;

    public function show(string $checkpoint): Response|RedirectResponse
    {
        $payload = $this->checkpointPayload(Auth::user(), $checkpoint);

        return match ($payload) {
            'unavailable' => redirect()->route('play.map'),
            'no_energy' => redirect()->route('beta.play.energy'),
            default => Inertia::render('BetaPlay/Checkpoint', $payload),
        };
    }

    /**
     * Os dados do desafio, ou por que ele não abre: 'unavailable' (ainda fechado) ou
     * 'no_energy' (sem energia; refazer também custa). 404 se não
     * existe na trilha do usuário. Com `$withBlocks`, cada material leva também
     * `summary_blocks`, o resumo em blocos para o app desenhar.
     *
     * @return array<string, mixed>|'unavailable'|'no_energy'
     */
    protected function checkpointPayload(User $user, string $checkpoint, bool $withBlocks = false): array|string
    {
        $found = $this->findCheckpoint($user, $checkpoint);

        abort_if(! $found, 404);

        if (! in_array($found['state'], ['available', 'complete'], true)) {
            return 'unavailable';
        }

        $energy = app(EnergyService::class);
        if (! $energy->has($user, (int) config('energy.challenge.open'))) {
            return 'no_energy';
        }
        $energy->startRun($user, ['kind' => 'challenge', 'id' => $found['key'], 'streak' => 0]);

        $progress = $this->progress($user, $found) ?? new UserBetaCheckpoint;
        $done = array_flip($progress->doneIds());
        $service = app(StudyMaterialService::class);

        return [
            'energy' => $energy->payload($user),
            'checkpoint' => [
                'key' => $found['key'],
                'section_number' => $found['section_number'],
                'step_number' => $found['step_number'],
                'total' => $found['total'],
                'completed' => $found['completed'],
            ],
            // Na ordem da lei; a tela volta ao primeiro passo ainda não feito neste quadrado
            'materials' => $this->materials($found)->map(function (StudyMaterial $material) use ($service, $done, $progress, $withBlocks) {
                $partial = $progress->partial($material->id);

                return [
                    'uuid' => $material->uuid,
                    'title' => $material->title,
                    'legislation_title' => $material->legislation->title,
                    'summary_html' => $service->renderSummary($material->summary),
                ] + ($withBlocks ? ['summary_blocks' => $service->summaryBlocks($material->summary)] : []) + [
                    // Sem gabarito, comentário nem fundamento: chegam depois de cada resposta
                    'questions' => $material->questions->map(fn (StudyQuestion $question) => [
                        'position' => $question->position,
                        'board' => $question->board,
                        'type' => $question->type,
                        'statement' => $question->statement,
                        'options' => $question->options,
                    ])->values(),
                    'done' => isset($done[$material->id]),
                    // O que ficou guardado de um material ainda não concluído
                    'summary_read' => $partial['summary'],
                    'answered' => array_map('intval', array_keys($partial['answers'])),
                ];
            })->values(),
        ];
    }

    /**
     * Resumo lido até o fim. Fica guardado para a pessoa não ter de ler de novo se sair
     * antes de responder as questões.
     */
    public function summary(Request $request, string $checkpoint): JsonResponse
    {
        $validated = $request->validate(['material' => ['required', 'uuid']]);

        $user = Auth::user();
        [$found, $material] = $this->authorizeMaterial($user, $checkpoint, $validated['material']);

        return response()->json($this->record($user, $found, $material, fn (array $partial) => ['summary' => true] + $partial));
    }

    /**
     * Corrige uma questão, devolve o gabarito comentado e guarda a resposta; vale a primeira
     * resposta dada a cada questão. Com a última questão o material fica concluído no quadrado
     * e o XP sai na hora, só na primeira conclusão. Em material já concluído (revisão) só
     * corrige.
     */
    public function answer(Request $request, string $checkpoint): JsonResponse
    {
        $validated = $request->validate([
            'material' => ['required', 'uuid'],
            'position' => ['required', 'integer'],
            'answer' => ['required', 'string', 'max:10'],
        ]);

        $user = Auth::user();
        [$found, $material] = $this->authorizeMaterial($user, $checkpoint, $validated['material']);

        $question = $material->questions->firstWhere('position', (int) $validated['position']);
        abort_if(! $question, 422, 'Questão inexistente.');

        $wasDone = in_array($material->id, $this->progress($user, $found)?->doneIds() ?? [], true);
        $answer = $validated['answer'];
        $isNew = false;
        $saved = $this->record($user, $found, $material, function (array $partial) use ($question, &$answer, &$isNew) {
            $isNew = ! isset($partial['answers'][$question->position]);
            $answer = $partial['answers'][$question->position] ?? $answer;
            $partial['answers'][$question->position] = $answer;

            return $partial;
        });

        $isCorrect = $this->isCorrect($question, $answer);

        // A questão nova gasta energia; refazer material já feito também (sem combo). Responder
        // de novo a mesma questão de material por fazer não cobra outra vez
        $energy = app(EnergyService::class);
        $spent = match (true) {
            $wasDone => $energy->challengeAnswer($user, $found['key'], $isCorrect, true, review: true),
            $isNew => $energy->challengeAnswer(
                $user,
                $found['key'],
                $isCorrect,
                $saved['checkpoint']['completed'] < $saved['checkpoint']['total'],
                material: $material->id,
                legislationId: $material->legislation_id,
            ),
            default => ['energy' => $energy->payload($user), 'combo' => null, 'out_of_energy' => false],
        };

        // O combo de quem assina rendeu XP agora (combo.xp; xp_gained segue sendo o do material)
        if (($spent['combo']['xp'] ?? 0) > 0) {
            $saved['user']['xp'] = $user->xp;
            $saved['discipline_level_up'] ??= app(XpService::class)
                ->detectDisciplineLevelUp($user, $material->legislation_id, $spent['combo']['xp']);
        }

        return response()->json([
            'is_correct' => $isCorrect,
            'correct_answer' => $question->correct_answer,
            'explanation' => $question->explanation,
            'legal_basis' => $question->legal_basis,
        ] + $saved + $spent);
    }

    // ==================== PRIVATE METHODS ====================

    /** O desafio do quadrado cujo último bloco tem esse uuid, na trilha do usuário. */
    private function findCheckpoint(User $user, string $key): ?array
    {
        foreach ($this->trail($user)['sections'] as $section) {
            foreach ($section['groups'] as $group) {
                if ($group['challenge']['key'] !== null && $group['challenge']['key'] === $key) {
                    return $group['challenge'] + [
                        'section_number' => $section['number'],
                        'step_number' => $group['total_steps'],
                    ];
                }
            }
        }

        return null;
    }

    /** As checagens de show() para as rotas JSON. */
    private function authorizedCheckpoint(User $user, string $key): array
    {
        $found = $this->findCheckpoint($user, $key);

        abort_if(! $found, 404);
        abort_if(! in_array($found['state'], ['available', 'complete'], true), 403, 'Conclua as fases deste quadrado para liberar o desafio.');

        return $found;
    }

    /**
     * O desafio e o material pedido, que precisa estar no desafio deste quadrado.
     *
     * @return array{0: array<string, mixed>, 1: StudyMaterial}
     */
    private function authorizeMaterial(User $user, string $key, string $materialUuid): array
    {
        $found = $this->authorizedCheckpoint($user, $key);

        $material = StudyMaterial::where('uuid', $materialUuid)->with('questions')->first();
        abort_if(! $material || ! in_array($material->id, $found['material_ids'], true), 404);

        return [$found, $material];
    }

    /**
     * Guarda um passo (resumo lido ou resposta) do material e, se com ele todas as questões
     * ficaram respondidas, conclui o material no quadrado e dá o XP da primeira conclusão.
     * Material já concluído no quadrado é revisão: nada é gravado.
     *
     * @param  callable(array{summary: bool, answers: array<int, string>}): array  $step  recebe e devolve o que já foi feito do material
     * @return array<string, mixed> o que a tela precisa saber depois do passo
     */
    private function record(User $user, array $found, StudyMaterial $material, callable $step): array
    {
        $key = ['user_id' => $user->id, 'legislation_segment_id' => $found['anchor_block_id']];
        UserBetaCheckpoint::firstOrCreate($key, ['done_material_ids' => []]);

        [$completed, $xp] = DB::transaction(function () use ($user, $key, $material, $step) {
            $progress = UserBetaCheckpoint::where($key)->lockForUpdate()->firstOrFail();
            $partial = $step($progress->partial($material->id));

            if (in_array($material->id, $progress->doneIds(), true)) {
                return [false, 0];
            }

            $pending = $progress->in_progress ?? [];
            $positions = $material->questions->pluck('position')->all();

            // Ainda falta questão: só guarda o passo
            if (array_diff($positions, array_keys($partial['answers']))) {
                $pending[$material->id] = $partial;
                $progress->update(['in_progress' => $pending]);

                return [false, 0];
            }

            unset($pending[$material->id]);
            $progress->update([
                'done_material_ids' => [...$progress->doneIds(), $material->id],
                'in_progress' => $pending ?: null,
            ]);

            $correct = $material->questions
                ->filter(fn (StudyQuestion $question) => $this->isCorrect($question, $partial['answers'][$question->position]))
                ->count();

            $record = UserStudyMaterial::where('user_id', $user->id)
                ->where('study_material_id', $material->id)
                ->lockForUpdate()
                ->first() ?? new UserStudyMaterial(['user_id' => $user->id, 'study_material_id' => $material->id]);

            $first = $record->completed_at === null;

            $record->fill([
                'answers' => $partial['answers'],
                'correct_count' => $correct,
                'completed_at' => $record->completed_at ?? now(),
            ])->save();

            if (! $first) {
                return [true, 0];
            }

            $xp = $user->boostedXp(self::MATERIAL_XP_BASE + self::MATERIAL_XP_PER_CORRECT * $correct);
            $user->addXp($xp, 'study_material', $material->id, $material->legislation_id);

            return [true, $xp];
        });

        return [
            'material_completed' => $completed,
            'xp_gained' => $xp,
            'discipline_level_up' => $xp > 0
                ? app(XpService::class)->detectDisciplineLevelUp($user, $material->legislation_id, $xp)
                : null,
            'checkpoint' => [
                'completed' => count(array_intersect($found['material_ids'], $this->progress($user, $found)?->doneIds() ?? [])),
                'total' => $found['total'],
            ],
            'user' => ['xp' => $user->xp],
        ];
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

    private function progress(User $user, array $checkpoint): ?UserBetaCheckpoint
    {
        return UserBetaCheckpoint::where('user_id', $user->id)
            ->where('legislation_segment_id', $checkpoint['anchor_block_id'])
            ->first();
    }

    private function isCorrect(StudyQuestion $question, string $answer): bool
    {
        return Str::lower(trim($answer)) === Str::lower($question->correct_answer);
    }
}
