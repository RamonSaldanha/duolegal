<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Legislation;
use App\Models\LegislationSegment;
use App\Models\StudyMaterial;
use App\Models\StudyQuestion;
use App\Services\StudyMaterialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rotas REST do agente de IA que grava material de estudo (ver a skill
 * `.claude/skills/gerar-material-estudo`). As mensagens de erro são escritas para o agente
 * conseguir se corrigir sozinho.
 */
class StudyMaterialController extends Controller
{
    public function __construct(private StudyMaterialService $service) {}

    public function legislations(): JsonResponse
    {
        $legislations = Legislation::where('status', 'published')->orderBy('title')->get();

        $counts = StudyMaterial::whereIn('legislation_id', $legislations->pluck('id'))
            ->selectRaw('legislation_id, status, COUNT(*) as total')
            ->groupBy('legislation_id', 'status')
            ->get()
            ->groupBy('legislation_id');

        return response()->json([
            'legislations' => $legislations->map(function (Legislation $legislation) use ($counts) {
                $layout = $this->service->layout($legislation);
                $byStatus = ($counts->get($legislation->id) ?? collect())->pluck('total', 'status');

                return [
                    'uuid' => $legislation->uuid,
                    'title' => $legislation->title,
                    'total_blocks' => count($layout),
                    'pending_blocks' => $this->service->pendingCount($layout),
                    'materials' => [
                        'draft' => (int) ($byStatus[StudyMaterial::STATUS_DRAFT] ?? 0),
                        'published' => (int) ($byStatus[StudyMaterial::STATUS_PUBLISHED] ?? 0),
                        'skipped' => (int) ($byStatus[StudyMaterial::STATUS_SKIPPED] ?? 0),
                    ],
                ];
            })->values(),
        ]);
    }

    public function next(Request $request): JsonResponse
    {
        $legislation = $this->findLegislation($request->query('legislation'));

        $layout = $this->service->layout($legislation);
        $run = array_slice($this->service->firstPendingRun($layout), 0, StudyMaterialService::NEXT_LIMIT);

        $base = [
            'legislation' => ['uuid' => $legislation->uuid, 'title' => $legislation->title],
            'total_blocks' => count($layout),
            'pending_blocks' => $this->service->pendingCount($layout),
        ];

        if (! $run) {
            return response()->json($base + [
                'complete' => true,
                'message' => 'Lei completa: não há blocos pendentes. Não há mais nada a gerar para esta lei.',
                'blocks' => [],
            ]);
        }

        $blocks = LegislationSegment::whereIn('id', $run)->orderBy('char_start')->orderBy('id')->get();

        $previous = StudyMaterial::where('study_materials.legislation_id', $legislation->id)
            ->where('study_materials.status', '!=', StudyMaterial::STATUS_SKIPPED)
            ->join('legislation_segments', 'legislation_segments.id', '=', 'study_materials.last_block_id')
            ->where('legislation_segments.char_start', '<', $blocks->first()->char_start)
            ->orderByDesc('legislation_segments.char_start')
            ->value('study_materials.title');

        return response()->json($base + [
            'complete' => false,
            'first_block_id' => $run[0],
            'previous_material_title' => $previous,
            'limits' => [
                'material_min_blocks' => StudyMaterialService::MIN_BLOCKS,
                'material_target_blocks' => StudyMaterialService::TARGET_BLOCKS,
                'material_max_blocks' => StudyMaterialService::MAX_BLOCKS,
                'skip_max_blocks' => StudyMaterialService::MAX_SKIP_BLOCKS,
            ],
            'blocks' => $this->formatBlocks($legislation, $blocks),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'legislation' => ['required', 'string'],
            'first_block_id' => ['required', 'integer'],
            'last_block_id' => ['required', 'integer'],
            'block_ids' => ['required', 'array'],
            'block_ids.*' => ['integer'],
            'generated_by_model' => ['nullable', 'string', 'max:100'],
            'skill_version' => ['nullable', 'string', 'max:30'],
            // Entra publicado; o agente só manda "draft" quando o usuário pede para revisar antes
            'status' => ['nullable', 'in:draft,published'],
        ], [
            'status.in' => 'status aceita "published" (padrão) ou "draft".',
            'block_ids.required' => 'block_ids não pode ficar vazio: informe os blocos do trecho que o material cobre.',
        ]);

        $legislation = $this->findLegislation($request->input('legislation'));
        $content = $this->service->validateContent($request->all());

        $material = DB::transaction(function () use ($request, $legislation, $content) {
            // Trava a lei: dois agentes ao mesmo tempo não gravam o mesmo trecho
            Legislation::whereKey($legislation->id)->lockForUpdate()->first();

            $range = $this->service->validateRange(
                $legislation,
                (int) $request->input('first_block_id'),
                (int) $request->input('last_block_id'),
                StudyMaterialService::MIN_BLOCKS,
                StudyMaterialService::MAX_BLOCKS,
            );
            $covered = $this->service->validateCoveredBlocks($range, $request->input('block_ids'));

            $material = StudyMaterial::create([
                'legislation_id' => $legislation->id,
                'first_block_id' => $range[0],
                'last_block_id' => end($range),
                'block_ids' => $covered,
                'content_hash' => $this->service->contentHash($covered),
                'title' => $content['title'],
                'summary' => $content['summary'],
                'status' => $request->input('status', StudyMaterial::STATUS_PUBLISHED),
                'published_at' => $request->input('status', StudyMaterial::STATUS_PUBLISHED) === StudyMaterial::STATUS_PUBLISHED ? now() : null,
                'generated_by_model' => $request->input('generated_by_model'),
                'skill_version' => $request->input('skill_version'),
            ]);

            $this->service->replaceQuestions($material, $content['questions']);

            return $material;
        });

        return response()->json([
            'message' => $material->status === StudyMaterial::STATUS_PUBLISHED
                ? 'Material gravado e publicado: já aparece no mapa.'
                : 'Material gravado como rascunho. O admin revisa e publica.',
            'material' => [
                'uuid' => $material->uuid,
                'status' => $material->status,
                'title' => $material->title,
                'first_block_id' => $material->first_block_id,
                'last_block_id' => $material->last_block_id,
                'covered_blocks' => count($material->block_ids),
            ],
            'pending_blocks' => $this->service->pendingCount($this->service->layout($legislation)),
        ], 201);
    }

    public function skip(Request $request): JsonResponse
    {
        $request->validate([
            'legislation' => ['required', 'string'],
            'first_block_id' => ['required', 'integer'],
            'last_block_id' => ['required', 'integer'],
            'skip_reason' => ['required', 'string', 'max:500'],
        ], [
            'skip_reason.required' => 'Informe skip_reason: um motivo curto para pular o trecho.',
        ]);

        $legislation = $this->findLegislation($request->input('legislation'));

        $material = DB::transaction(function () use ($request, $legislation) {
            Legislation::whereKey($legislation->id)->lockForUpdate()->first();

            $range = $this->service->validateRange(
                $legislation,
                (int) $request->input('first_block_id'),
                (int) $request->input('last_block_id'),
                1,
                StudyMaterialService::MAX_SKIP_BLOCKS,
            );

            return StudyMaterial::create([
                'legislation_id' => $legislation->id,
                'first_block_id' => $range[0],
                'last_block_id' => end($range),
                'block_ids' => [],
                'status' => StudyMaterial::STATUS_SKIPPED,
                'skip_reason' => trim($request->input('skip_reason')),
            ]);
        });

        return response()->json([
            'message' => 'Trecho marcado como pulado.',
            'material' => [
                'uuid' => $material->uuid,
                'status' => $material->status,
                'first_block_id' => $material->first_block_id,
                'last_block_id' => $material->last_block_id,
            ],
            'pending_blocks' => $this->service->pendingCount($this->service->layout($legislation)),
        ], 201);
    }

    public function status(Request $request): JsonResponse
    {
        $legislation = $this->findLegislation($request->query('legislation'));
        $layout = $this->service->layout($legislation);

        $byStatus = StudyMaterial::where('legislation_id', $legislation->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byBoard = StudyQuestion::query()
            ->join('study_materials', 'study_materials.id', '=', 'study_questions.study_material_id')
            ->where('study_materials.legislation_id', $legislation->id)
            ->selectRaw('study_questions.board, COUNT(*) as total')
            ->groupBy('study_questions.board')
            ->pluck('total', 'board');

        $pending = $this->service->pendingCount($layout);

        return response()->json([
            'legislation' => ['uuid' => $legislation->uuid, 'title' => $legislation->title],
            'total_blocks' => count($layout),
            'pending_blocks' => $pending,
            'complete' => $pending === 0,
            'materials' => [
                'draft' => (int) ($byStatus[StudyMaterial::STATUS_DRAFT] ?? 0),
                'published' => (int) ($byStatus[StudyMaterial::STATUS_PUBLISHED] ?? 0),
                'skipped' => (int) ($byStatus[StudyMaterial::STATUS_SKIPPED] ?? 0),
            ],
            'questions_by_board' => collect(StudyQuestion::BOARDS)
                ->mapWithKeys(fn ($board) => [$board => (int) ($byBoard[$board] ?? 0)]),
        ]);
    }

    private function findLegislation(mixed $uuid): Legislation
    {
        $legislation = is_string($uuid) && $uuid !== '' ? Legislation::where('uuid', $uuid)->first() : null;

        if (! $legislation) {
            throw ValidationException::withMessages([
                'legislation' => 'Lei não encontrada. Informe em "legislation" o uuid devolvido por GET /legislations.',
            ]);
        }

        return $legislation;
    }

    /**
     * Blocos para o agente. `heading_before` é o texto da lei entre o bloco anterior e este
     * (títulos, capítulos e seções costumam ficar ali), quando existe e é curto.
     */
    private function formatBlocks(Legislation $legislation, $blocks): array
    {
        $previousEnd = LegislationSegment::where('legislation_id', $legislation->id)
            ->where('is_block', true)
            ->where('char_start', '<', $blocks->first()->char_start)
            ->orderByDesc('char_start')
            ->value('char_end');

        $formatted = [];
        foreach ($blocks as $block) {
            $heading = null;
            if ($previousEnd !== null && $block->char_start > $previousEnd) {
                $between = trim(mb_substr((string) $legislation->raw_text, $previousEnd, $block->char_start - $previousEnd));
                $heading = $between !== '' && mb_strlen($between) <= 400 ? $between : null;
            }

            $formatted[] = [
                'id' => $block->id,
                'article_reference' => $block->article_reference,
                'structural_marker' => $block->structural_marker,
                'segment_type' => $block->segment_type,
                'heading_before' => $heading,
                'text' => $block->original_text,
            ];

            $previousEnd = $block->char_end;
        }

        return $formatted;
    }
}
