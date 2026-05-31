<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Legislation;
use App\Models\LegislationSegment;
use App\Models\UserLegislationSelection;
use App\Models\UserSegmentProgress;
use App\Services\PhaseGenerationService;
use App\Services\StreakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayController extends Controller
{
    public function __construct(
        private PhaseGenerationService $phaseService,
        private StreakService $streakService,
    ) {}

    /**
     * GET /v1/play/map
     * Retorna o mapa paginado em torno da fase atual.
     */
    public function map(Request $request): JsonResponse
    {
        $user = $request->user();

        $allPublished = Legislation::where('status', 'published')
            ->withCount(['segments as total_blocks' => fn ($q) => $q->where('is_block', true)])
            ->orderBy('id')
            ->get();

        $selectedUuids = $this->loadSelectedUuids($user, $allPublished);

        $allPhases = $this->phaseService->generatePhaseList($user, $selectedUuids);

        if (empty($allPhases)) {
            return response()->json([
                'phases' => [],
                'legislations' => $this->mapLegislations($allPublished),
                'selectedLegislationUuids' => $selectedUuids,
                'currentPhaseId' => null,
                'hasMoreAbove' => false,
                'hasMoreBelow' => false,
                'totalPhases' => 0,
                'user' => $this->userPayload($user),
            ]);
        }

        $currentPhaseId = null;
        foreach ($allPhases as $phase) {
            if ($phase['is_current']) {
                $currentPhaseId = $phase['id'];
                break;
            }
        }

        $totalPhases = count($allPhases);
        $currentIndex = 0;
        if ($currentPhaseId !== null) {
            foreach ($allPhases as $i => $phase) {
                if ($phase['id'] === $currentPhaseId) {
                    $currentIndex = $i;
                    break;
                }
            }
        }

        $halfPage = (int) floor(PhaseGenerationService::PHASES_PER_PAGE / 2);
        $startIndex = max(0, $currentIndex - $halfPage);
        $endIndex = min($totalPhases, $startIndex + PhaseGenerationService::PHASES_PER_PAGE);
        $startIndex = max(0, $endIndex - PhaseGenerationService::PHASES_PER_PAGE);

        $slice = array_values(array_slice($allPhases, $startIndex, $endIndex - $startIndex));
        $slice = array_map(fn ($p) => $this->phaseService->stripBlockIds($p), $slice);

        return response()->json([
            'phases' => $slice,
            'legislations' => $this->mapLegislations($allPublished),
            'selectedLegislationUuids' => $selectedUuids,
            'currentPhaseId' => $currentPhaseId,
            'hasMoreAbove' => $startIndex > 0,
            'hasMoreBelow' => $endIndex < $totalPhases,
            'totalPhases' => $totalPhases,
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * GET /v1/play/map/more
     * Carrega mais fases acima ou abaixo de um cursor (infinite scroll).
     */
    public function loadMapPhases(Request $request): JsonResponse
    {
        $user = $request->user();
        $direction = $request->get('direction', 'below');
        $cursor = (int) $request->get('cursor', 0);
        $limit = min((int) $request->get('limit', 20), 40);

        $selectedUuids = $this->loadSelectedUuids($user, null);
        $allPhases = $this->phaseService->generatePhaseList($user, $selectedUuids);

        $cursorIndex = null;
        foreach ($allPhases as $i => $phase) {
            if ($phase['id'] === $cursor) {
                $cursorIndex = $i;
                break;
            }
        }

        if ($cursorIndex === null) {
            return response()->json(['phases' => [], 'hasMore' => false]);
        }

        if ($direction === 'below') {
            $startIndex = $cursorIndex + 1;
            $slice = array_slice($allPhases, $startIndex, $limit);
            $hasMore = ($startIndex + $limit) < count($allPhases);
        } else {
            $endIndex = $cursorIndex;
            $startIndex = max(0, $endIndex - $limit);
            $slice = array_slice($allPhases, $startIndex, $endIndex - $startIndex);
            $hasMore = $startIndex > 0;
        }

        $slice = array_map(fn ($p) => $this->phaseService->stripBlockIds($p), array_values($slice));

        return response()->json([
            'phases' => $slice,
            'hasMore' => $hasMore,
        ]);
    }

    /**
     * GET /v1/play/phase/{phaseId}
     * Retorna a fase com segmentos completos (contexto) e o segmento ativo.
     */
    public function phase(Request $request, int $phaseId): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasLives()) {
            return response()->json([
                'message' => 'Sem vidas.',
                'should_redirect' => true,
                'redirect' => 'nolives',
            ], 403);
        }

        $selectedUuids = $this->loadSelectedUuids($user, null);
        $allPhases = $this->phaseService->generatePhaseList($user, $selectedUuids);

        $phase = null;
        $phaseIndex = null;
        foreach ($allPhases as $i => $p) {
            if ($p['id'] === $phaseId) {
                $phase = $p;
                $phaseIndex = $i;
                break;
            }
        }

        if (! $phase) {
            return response()->json(['message' => 'Fase não encontrada.'], 404);
        }

        if ($phase['is_blocked'] && ! $phase['is_current']) {
            return response()->json(['message' => 'Esta fase está bloqueada.'], 403);
        }

        $blocks = LegislationSegment::whereIn('id', $phase['block_ids'])
            ->where('is_block', true)
            ->orderBy('char_start')
            ->with('lacunas')
            ->get();

        if ($blocks->isEmpty()) {
            return response()->json(['message' => 'Sem blocos nesta fase.'], 404);
        }

        $blockIds = $blocks->pluck('id');
        $progressRecords = UserSegmentProgress::where('user_id', $user->id)
            ->whereIn('legislation_segment_id', $blockIds)
            ->get()
            ->keyBy('legislation_segment_id');

        $activeIndex = $blocks->count() - 1;
        foreach ($blocks as $i => $block) {
            $progress = $progressRecords->get($block->id);
            if (! $progress || ! $progress->is_completed) {
                $activeIndex = $i;
                break;
            }
        }

        $completedInPhase = $blocks->slice(0, $activeIndex)->reverse()->take(5)->reverse();

        $legislation = Legislation::where('uuid', $phase['legislation_uuid'])->first();
        $firstBlockCharStart = $blocks->first()->char_start;

        $previousBlocks = $legislation->segments()
            ->where('is_block', true)
            ->where('char_start', '<', $firstBlockCharStart)
            ->orderBy('char_start', 'desc')
            ->limit(max(0, 5 - $completedInPhase->count()))
            ->with('lacunas')
            ->get()
            ->reverse()
            ->values();

        $allPreviousBlockIds = $previousBlocks->pluck('id')->merge($completedInPhase->pluck('id'));
        $allPreviousProgress = UserSegmentProgress::where('user_id', $user->id)
            ->whereIn('legislation_segment_id', $allPreviousBlockIds)
            ->get()
            ->keyBy('legislation_segment_id');

        $completedData = $previousBlocks
            ->merge($completedInPhase)
            ->map(fn ($seg) => $this->formatSegmentWithProgress($seg, $allPreviousProgress))
            ->values();

        $activeSegment = $blocks->get($activeIndex);
        $allComplete = $activeIndex === $blocks->count() - 1
            && ($progressRecords->get($blocks->last()->id)?->is_completed ?? false);

        $nextPhaseId = null;
        if ($phaseIndex !== null && isset($allPhases[$phaseIndex + 1])) {
            $nextPhaseId = $allPhases[$phaseIndex + 1]['id'];
        }

        $hasMoreAbove = $legislation->segments()
            ->where('is_block', true)
            ->where('char_start', '<', $previousBlocks->isNotEmpty() ? $previousBlocks->first()->char_start : $firstBlockCharStart)
            ->exists();

        return response()->json([
            'legislation' => [
                'uuid' => $legislation->uuid,
                'title' => $legislation->title,
            ],
            'completedSegments' => $completedData,
            'activeSegment' => $allComplete ? null : $this->formatSegmentForChallenge($activeSegment),
            'progress' => [
                'current' => $activeIndex + 1,
                'total' => $blocks->count(),
                'percentage' => round(($activeIndex / max(1, $blocks->count())) * 100),
            ],
            'hasMoreAbove' => $hasMoreAbove,
            'allComplete' => $allComplete,
            'phaseId' => $phaseId,
            'nextPhaseId' => $nextPhaseId,
            'phaseBlockUuids' => $blocks->pluck('uuid')->toArray(),
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * GET /v1/play/phase/{phaseId}/more-above
     * Carrega segmentos completos anteriores (scroll para cima).
     */
    public function loadMoreSegments(Request $request, int $phaseId): JsonResponse
    {
        $user = $request->user();
        $beforePosition = (int) $request->get('before_position', 0);
        $legislationUuid = $request->get('legislation_uuid');
        $limit = min((int) $request->get('limit', 5), 20);

        if (! $legislationUuid) {
            return response()->json(['segments' => [], 'hasMore' => false]);
        }

        $legislation = Legislation::where('uuid', $legislationUuid)->first();
        if (! $legislation) {
            return response()->json(['segments' => [], 'hasMore' => false]);
        }

        $blocks = $legislation->segments()
            ->where('is_block', true)
            ->where('char_start', '<', $beforePosition)
            ->orderBy('char_start', 'desc')
            ->limit($limit)
            ->with('lacunas')
            ->get()
            ->reverse()
            ->values();

        $blockIds = $blocks->pluck('id');
        $progressRecords = UserSegmentProgress::where('user_id', $user->id)
            ->whereIn('legislation_segment_id', $blockIds)
            ->get()
            ->keyBy('legislation_segment_id');

        $data = $blocks->map(fn ($seg) => $this->formatSegmentWithProgress($seg, $progressRecords));

        $hasMore = $legislation->segments()
            ->where('is_block', true)
            ->where('char_start', '<', $blocks->first()?->char_start ?? 0)
            ->exists();

        return response()->json([
            'segments' => $data->values(),
            'hasMore' => $hasMore,
        ]);
    }

    /**
     * POST /v1/play/submit
     * Processa a resposta de uma lacuna.
     */
    public function submitAnswer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'segment_uuid' => 'required|string|exists:legislation_segments,uuid',
            'answers' => 'present|array',
            'answers.*.gap_order' => 'required|integer',
            'answers.*.word_position' => 'required|integer',
            'answers.*.correct_word' => 'required|string',
            'answers.*.user_word' => 'required|string',
            'answers.*.is_correct' => 'required|boolean',
            'correct_count' => 'required|integer|min:0',
            'total_count' => 'required|integer|min:0',
            'phase_block_uuids' => 'nullable|array',
            'phase_block_uuids.*' => 'string',
        ]);

        $segment = LegislationSegment::where('uuid', $validated['segment_uuid'])->firstOrFail();
        $user = $request->user();

        $correctCount = (int) $validated['correct_count'];
        $totalCount = (int) $validated['total_count'];
        $percentage = ($totalCount > 0) ? (($correctCount / $totalCount) * 100) : 100;

        $previousProgress = UserSegmentProgress::where('user_id', $user->id)
            ->where('legislation_segment_id', $segment->id)
            ->first();
        $wasAlreadyCompleted = $previousProgress && $previousProgress->is_completed;

        $lostLife = false;
        if ($percentage < 70) {
            if ($user->lives > 0 && ! $user->hasInfiniteLives()) {
                $user->decrementLife();
                $lostLife = true;
            }
        }

        $progress = UserSegmentProgress::updateProgress(
            $user->id,
            $segment->id,
            $correctCount,
            $totalCount,
            $validated['answers']
        );

        $xpGained = 0;
        $levelUp = null;
        if ($percentage >= 70 && ! $wasAlreadyCompleted) {
            $gapsCount = $segment->lacunas()->where('is_correct', true)->count();
            $xpGained = max(10, $gapsCount * 5);
            $user->addXp($xpGained, 'legislation', $segment->id, $segment->legislation_id);

            // Detecta subida de nível na disciplina (após creditar o XP).
            $levelUp = app(\App\Services\XpService::class)
                ->detectDisciplineLevelUp($user, $segment->legislation_id, $xpGained);
        }
        // A ofensiva é derivada das XpTransactions (ver StreakService); o addXp
        // acima já registra o "dia estudado" automaticamente.

        $nextSegment = null;
        $phaseBlockUuids = $validated['phase_block_uuids'] ?? null;

        if ($phaseBlockUuids && ! empty($phaseBlockUuids)) {
            $nextSegment = LegislationSegment::whereIn('uuid', $phaseBlockUuids)
                ->where('is_block', true)
                ->where('char_start', '>', $segment->char_start)
                ->orderBy('char_start')
                ->with('lacunas')
                ->first();
        } else {
            $legislation = $segment->legislation;
            $nextSegment = $legislation->segments()
                ->where('is_block', true)
                ->where('char_start', '>', $segment->char_start)
                ->orderBy('char_start')
                ->with('lacunas')
                ->first();
        }

        $shouldRedirect = ! $user->hasInfiniteLives() && $user->lives <= 0;

        return response()->json([
            'success' => true,
            'progress' => [
                'is_completed' => $progress->is_completed,
                'best_score' => $progress->best_score,
                'attempts' => $progress->attempts,
                'percentage' => $progress->best_score,
            ],
            'answers' => $validated['answers'],
            'next_segment' => $nextSegment ? $this->formatSegmentForChallenge($nextSegment) : null,
            'user' => $this->userPayload($user),
            'xp_gained' => $xpGained,
            'discipline_level_up' => $levelUp,
            'lost_life' => $lostLife,
            'should_redirect' => $shouldRedirect,
            'redirect_url' => $shouldRedirect ? '/play/nolives' : null,
        ]);
    }

    /**
     * POST /v1/play/reward-life
     */
    public function rewardLife(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->lives = min($user->lives + 1, 5);
        $user->save();

        return response()->json([
            'success' => true,
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * GET /v1/play/preferences
     * Retorna todas as legislações com progresso e estado de seleção.
     */
    public function preferences(Request $request): JsonResponse
    {
        $user = $request->user();

        $legislations = Legislation::where('status', 'published')
            ->orderBy('title')
            ->get();

        $selectedIds = UserLegislationSelection::where('user_id', $user->id)
            ->pluck('legislation_id')
            ->toArray();

        $legislationIds = $legislations->pluck('id')->toArray();

        $totalBlocks = LegislationSegment::whereIn('legislation_id', $legislationIds)
            ->where('is_block', true)
            ->selectRaw('legislation_id, COUNT(*) as total')
            ->groupBy('legislation_id')
            ->pluck('total', 'legislation_id')
            ->toArray();

        $completedBlocks = LegislationSegment::whereIn('legislation_segments.legislation_id', $legislationIds)
            ->where('legislation_segments.is_block', true)
            ->join('user_segment_progress', function ($join) use ($user) {
                $join->on('user_segment_progress.legislation_segment_id', '=', 'legislation_segments.id')
                    ->where('user_segment_progress.user_id', '=', $user->id)
                    ->where('user_segment_progress.is_completed', '=', true);
            })
            ->selectRaw('legislation_segments.legislation_id, COUNT(*) as completed')
            ->groupBy('legislation_segments.legislation_id')
            ->pluck('completed', 'legislation_id')
            ->toArray();

        $data = $legislations->map(function ($leg) use ($totalBlocks, $completedBlocks, $selectedIds) {
            $total = $totalBlocks[$leg->id] ?? 0;
            $completed = $completedBlocks[$leg->id] ?? 0;

            return [
                'id' => $leg->id,
                'uuid' => $leg->uuid,
                'title' => $leg->title,
                'total_blocks' => $total,
                'completed_blocks' => $completed,
                'percentage' => $total > 0 ? round(($completed / $total) * 100) : 0,
                'is_selected' => in_array($leg->id, $selectedIds),
            ];
        });

        return response()->json([
            'legislations' => $data,
        ]);
    }

    /**
     * PUT /v1/play/preferences
     * Salva os UUIDs selecionados.
     */
    public function savePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'selected_uuids' => 'present|array',
            'selected_uuids.*' => 'string|exists:legislations,uuid',
        ]);

        $user = $request->user();

        $legislationIds = Legislation::whereIn('uuid', $validated['selected_uuids'])
            ->where('status', 'published')
            ->pluck('id')
            ->toArray();

        UserLegislationSelection::where('user_id', $user->id)->delete();

        foreach ($legislationIds as $legislationId) {
            UserLegislationSelection::create([
                'user_id' => $user->id,
                'legislation_id' => $legislationId,
            ]);
        }

        return response()->json([
            'success' => true,
            'selectedLegislationUuids' => $validated['selected_uuids'],
        ]);
    }

    // ==================== HELPERS ====================

    private function loadSelectedUuids($user, $allPublished = null): array
    {
        $selectedUuids = UserLegislationSelection::where('user_id', $user->id)
            ->join('legislations', 'legislations.id', '=', 'user_legislation_selections.legislation_id')
            ->pluck('legislations.uuid')
            ->toArray();

        if (empty($selectedUuids)) {
            $selectedUuids = $allPublished
                ? $allPublished->pluck('uuid')->toArray()
                : Legislation::where('status', 'published')->pluck('uuid')->toArray();
        }

        return $selectedUuids;
    }

    private function mapLegislations($legislations): array
    {
        return $legislations->map(fn ($l) => [
            'uuid' => $l->uuid,
            'title' => $l->title,
            'total_blocks' => $l->total_blocks,
        ])->toArray();
    }

    private function userPayload($user): array
    {
        return [
            'lives' => $user->lives,
            'has_infinite_lives' => $user->hasInfiniteLives(),
            'xp' => $user->xp,
            'current_streak' => $this->streakService->currentStreak($user->id),
            'longest_streak' => $this->streakService->longestStreak($user->id),
        ];
    }

    private function formatSegmentWithProgress(LegislationSegment $segment, $progressRecords): array
    {
        $progress = $progressRecords->get($segment->id);

        return [
            'uuid' => $segment->uuid,
            'original_text' => $segment->original_text,
            'position' => $segment->char_start,
            'segment_type' => $segment->segment_type,
            'article_reference' => $segment->article_reference,
            'structural_marker' => $segment->structural_marker,
            'answers' => $progress?->answers,
            'is_completed' => $progress?->is_completed ?? false,
            'best_score' => $progress?->best_score ?? 0,
        ];
    }

    private function formatSegmentForChallenge(LegislationSegment $segment): array
    {
        $lacunas = $segment->lacunas()->orderBy('gap_order')->get();
        $correctLacunas = $lacunas->where('is_correct', true)->sortBy('gap_order');
        $distractors = $lacunas->where('is_correct', false);

        $allOptions = $correctLacunas->pluck('word')
            ->merge($distractors->pluck('word'))
            ->shuffle()
            ->values();

        return [
            'uuid' => $segment->uuid,
            'original_text' => $segment->original_text,
            'position' => $segment->char_start,
            'segment_type' => $segment->segment_type,
            'article_reference' => $segment->article_reference,
            'structural_marker' => $segment->structural_marker,
            'lacunas' => $correctLacunas->map(fn ($l) => [
                'gap_order' => $l->gap_order,
                'word' => $l->word,
                'word_position' => $l->word_position,
            ])->values()->toArray(),
            'options' => $allOptions->toArray(),
            'total_gaps' => $correctLacunas->count(),
        ];
    }
}
