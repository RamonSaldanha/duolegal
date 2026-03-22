<?php

namespace App\Http\Controllers;

use App\Models\Legislation;
use App\Models\LegislationSegment;
use App\Models\UserLegislationSelection;
use App\Models\UserSegmentProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LegislationPlayController extends Controller
{
    const BLOCKS_PER_PHASE = 15;
    const PHASES_PER_LEGISLATION_SWITCH = 4;
    const PHASES_PER_PAGE = 30;

    /**
     * Mapa com trilha intercalada de legislações.
     */
    public function map()
    {
        $user = Auth::user();

        if (! $user->hasLives()) {
            return redirect()->route('play.nolives');
        }

        // Todas as legislações publicadas (para o filtro)
        $allPublished = Legislation::where('status', 'published')
            ->withCount(['segments as total_blocks' => fn ($q) => $q->where('is_block', true)])
            ->orderBy('id')
            ->get();

        // Seleção do usuário salva no banco
        $selectedUuids = UserLegislationSelection::where('user_id', $user->id)
            ->join('legislations', 'legislations.id', '=', 'user_legislation_selections.legislation_id')
            ->pluck('legislations.uuid')
            ->toArray();

        // Se não há seleção salva, usar todas
        if (empty($selectedUuids)) {
            $selectedUuids = $allPublished->pluck('uuid')->toArray();
        }

        // Gerar lista de fases intercaladas
        $allPhases = $this->generatePhaseList($user, $selectedUuids);

        // Encontrar a fase atual
        $currentPhaseId = null;
        foreach ($allPhases as $phase) {
            if ($phase['is_current']) {
                $currentPhaseId = $phase['id'];
                break;
            }
        }

        // Slice inicial centrado na fase atual
        $totalPhases = count($allPhases);
        $currentIndex = 0;
        if ($currentPhaseId) {
            foreach ($allPhases as $i => $phase) {
                if ($phase['id'] === $currentPhaseId) {
                    $currentIndex = $i;
                    break;
                }
            }
        }

        $halfPage = (int) floor(self::PHASES_PER_PAGE / 2);
        $startIndex = max(0, $currentIndex - $halfPage);
        $endIndex = min($totalPhases, $startIndex + self::PHASES_PER_PAGE);
        // Ajustar start se estamos perto do final
        $startIndex = max(0, $endIndex - self::PHASES_PER_PAGE);

        $initialSlice = array_values(array_slice($allPhases, $startIndex, $endIndex - $startIndex));

        // Remover block_ids do payload enviado ao frontend
        $initialSlice = array_map(fn ($p) => $this->stripBlockIds($p), $initialSlice);

        return Inertia::render('LegislationPlay/Map', [
            'phases' => $initialSlice,
            'legislations' => $allPublished->map(fn ($l) => [
                'uuid' => $l->uuid,
                'title' => $l->title,
                'total_blocks' => $l->total_blocks,
            ]),
            'selectedLegislationUuids' => $selectedUuids,
            'currentPhaseId' => $currentPhaseId,
            'hasMoreAbove' => $startIndex > 0,
            'hasMoreBelow' => $endIndex < $totalPhases,
            'totalPhases' => $totalPhases,
            'user' => [
                'lives' => $user->lives,
                'has_infinite_lives' => $user->hasInfiniteLives(),
                'xp' => $user->xp,
            ],
        ]);
    }

    /**
     * Endpoint AJAX para infinite scroll no mapa.
     */
    public function loadMapPhases(Request $request)
    {
        $user = Auth::user();
        $direction = $request->get('direction', 'below');
        $cursor = (int) $request->get('cursor', 0);
        $limit = min((int) $request->get('limit', 20), 40);

        // Carregar seleção do usuário
        $selectedUuids = UserLegislationSelection::where('user_id', $user->id)
            ->join('legislations', 'legislations.id', '=', 'user_legislation_selections.legislation_id')
            ->pluck('legislations.uuid')
            ->toArray();

        if (empty($selectedUuids)) {
            $selectedUuids = Legislation::where('status', 'published')->pluck('uuid')->toArray();
        }

        $allPhases = $this->generatePhaseList($user, $selectedUuids);

        // Encontrar posição do cursor
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

        $slice = array_map(fn ($p) => $this->stripBlockIds($p), array_values($slice));

        return response()->json([
            'phases' => $slice,
            'hasMore' => $hasMore,
        ]);
    }

    /**
     * Salvar filtro de legislações selecionadas pelo usuário.
     */
    public function saveFilter(Request $request)
    {
        $validated = $request->validate([
            'legislation_uuids' => 'present|array',
            'legislation_uuids.*' => 'string|exists:legislations,uuid',
        ]);

        $user = Auth::user();

        // Buscar IDs das legislações pelos UUIDs
        $legislationIds = Legislation::whereIn('uuid', $validated['legislation_uuids'])
            ->where('status', 'published')
            ->pluck('id')
            ->toArray();

        // Sync: deletar existentes e inserir novos
        UserLegislationSelection::where('user_id', $user->id)->delete();

        foreach ($legislationIds as $legislationId) {
            UserLegislationSelection::create([
                'user_id' => $user->id,
                'legislation_id' => $legislationId,
            ]);
        }

        return redirect()->route('play.map');
    }

    /**
     * Página de preferências de legislações (seleção + progresso).
     */
    public function preferences()
    {
        $user = Auth::user();

        $legislations = Legislation::where('status', 'published')
            ->orderBy('title')
            ->get();

        // Seleções atuais do usuário
        $selectedIds = UserLegislationSelection::where('user_id', $user->id)
            ->pluck('legislation_id')
            ->toArray();

        // Calcular progresso por legislação em batch
        $legislationIds = $legislations->pluck('id')->toArray();

        // Total de blocos por legislação
        $totalBlocks = LegislationSegment::whereIn('legislation_id', $legislationIds)
            ->where('is_block', true)
            ->selectRaw('legislation_id, COUNT(*) as total')
            ->groupBy('legislation_id')
            ->pluck('total', 'legislation_id')
            ->toArray();

        // Blocos completados por legislação
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

        $legislationsData = $legislations->map(function ($leg) use ($totalBlocks, $completedBlocks, $selectedIds) {
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

        return Inertia::render('LegislationPlay/Preferences', [
            'legislations' => $legislationsData,
        ]);
    }

    /**
     * Salvar preferências de legislações.
     */
    public function savePreferences(Request $request)
    {
        $validated = $request->validate([
            'legislation_ids' => 'required|array|min:1',
            'legislation_ids.*' => 'integer|exists:legislations,id',
        ]);

        $user = Auth::user();

        // Verificar que são legislações publicadas
        $validIds = Legislation::whereIn('id', $validated['legislation_ids'])
            ->where('status', 'published')
            ->pluck('id')
            ->toArray();

        // Sync: deletar existentes e inserir novos
        UserLegislationSelection::where('user_id', $user->id)->delete();

        foreach ($validIds as $legislationId) {
            UserLegislationSelection::create([
                'user_id' => $user->id,
                'legislation_id' => $legislationId,
            ]);
        }

        return redirect()->back();
    }

    /**
     * Jogar uma fase específica (grupo de blocos).
     */
    public function playPhase(int $phaseId, Request $request)
    {
        $user = Auth::user();

        if (! $user->hasLives()) {
            return redirect()->route('play.nolives');
        }

        // Carregar seleção do usuário
        $selectedUuids = UserLegislationSelection::where('user_id', $user->id)
            ->join('legislations', 'legislations.id', '=', 'user_legislation_selections.legislation_id')
            ->pluck('legislations.uuid')
            ->toArray();

        if (empty($selectedUuids)) {
            $selectedUuids = Legislation::where('status', 'published')->pluck('uuid')->toArray();
        }

        // Gerar lista de fases
        $allPhases = $this->generatePhaseList($user, $selectedUuids);

        // Encontrar a fase solicitada
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
            return redirect()->route('play.map');
        }

        // Verificar se não está bloqueada (exceto se é a atual)
        if ($phase['is_blocked'] && ! $phase['is_current']) {
            return redirect()->route('play.map');
        }

        // Carregar blocos da fase com lacunas
        $blocks = LegislationSegment::whereIn('id', $phase['block_ids'])
            ->where('is_block', true)
            ->orderBy('char_start')
            ->with('lacunas')
            ->get();

        if ($blocks->isEmpty()) {
            return redirect()->route('play.map');
        }

        // Progresso do usuário para os blocos da fase
        $blockIds = $blocks->pluck('id');
        $progressRecords = UserSegmentProgress::where('user_id', $user->id)
            ->whereIn('legislation_segment_id', $blockIds)
            ->get()
            ->keyBy('legislation_segment_id');

        // Encontrar primeiro bloco incompleto
        $activeIndex = $blocks->count() - 1;
        foreach ($blocks as $i => $block) {
            $progress = $progressRecords->get($block->id);
            if (! $progress || ! $progress->is_completed) {
                $activeIndex = $i;
                break;
            }
        }

        // Blocos completados da fase + contexto de fases anteriores da mesma legislação
        $completedInPhase = $blocks->slice(0, $activeIndex)->reverse()->take(5)->reverse();

        // Também carregar blocos de fases anteriores da mesma legislação para contexto
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

        // Combinar blocos anteriores + completados da fase
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

        // Encontrar próxima fase
        $nextPhaseId = null;
        if ($phaseIndex !== null && isset($allPhases[$phaseIndex + 1])) {
            $nextPhaseId = $allPhases[$phaseIndex + 1]['id'];
        }

        // Verificar se há mais blocos anteriores para scroll
        $hasMoreAbove = $legislation->segments()
            ->where('is_block', true)
            ->where('char_start', '<', $previousBlocks->isNotEmpty() ? $previousBlocks->first()->char_start : $firstBlockCharStart)
            ->exists();

        return Inertia::render('LegislationPlay/Play', [
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
            'user' => [
                'lives' => $user->lives,
                'has_infinite_lives' => $user->hasInfiniteLives(),
                'xp' => $user->xp,
            ],
        ]);
    }

    /**
     * Tela principal do jogo (legacy) — carrega segmento ativo e últimos completados.
     */
    public function play(Legislation $legislation)
    {
        $user = Auth::user();

        if (! $user->hasLives()) {
            return redirect()->route('play.nolives');
        }

        if ($legislation->status !== 'published') {
            return redirect()->route('play.map');
        }

        $blocks = $legislation->segments()
            ->where('is_block', true)
            ->orderBy('char_start')
            ->with('lacunas')
            ->get();

        if ($blocks->isEmpty()) {
            return redirect()->route('play.map');
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

        $completedSlice = $blocks->slice(0, $activeIndex)->reverse()->take(5)->reverse();
        $completedData = $completedSlice->map(fn ($seg) => $this->formatSegmentWithProgress($seg, $progressRecords))->values();

        $activeSegment = $blocks->get($activeIndex);
        $allComplete = $activeIndex === $blocks->count() - 1
            && ($progressRecords->get($blocks->last()->id)?->is_completed ?? false);

        return Inertia::render('LegislationPlay/Play', [
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
            'hasMoreAbove' => $activeIndex > 5,
            'allComplete' => $allComplete,
            'user' => [
                'lives' => $user->lives,
                'has_infinite_lives' => $user->hasInfiniteLives(),
                'xp' => $user->xp,
            ],
        ]);
    }

    /**
     * Endpoint JSON para scroll infinito — carrega segmentos completados anteriores.
     */
    public function loadCompletedSegments(Request $request, Legislation $legislation)
    {
        $user = Auth::user();
        $beforePosition = (int) $request->get('before_position', 0);
        $limit = min((int) $request->get('limit', 5), 20);

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
     * Processa a resposta do jogador.
     */
    public function submitAnswer(Request $request)
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
        $user = Auth::user();

        $correctCount = (int) $validated['correct_count'];
        $totalCount = (int) $validated['total_count'];
        $percentage = ($totalCount > 0) ? (($correctCount / $totalCount) * 100) : 100;

        // Verifica se já havia completado antes
        $previousProgress = UserSegmentProgress::where('user_id', $user->id)
            ->where('legislation_segment_id', $segment->id)
            ->first();
        $wasAlreadyCompleted = $previousProgress && $previousProgress->is_completed;

        // Perde vida se < 70%
        $lostLife = false;
        if ($percentage < 70) {
            if ($user->lives > 0 && ! $user->hasInfiniteLives()) {
                $user->decrementLife();
                $lostLife = true;
            }
        }

        // Atualiza progresso
        $progress = UserSegmentProgress::updateProgress(
            $user->id,
            $segment->id,
            $correctCount,
            $totalCount,
            $validated['answers']
        );

        // XP na primeira conclusão
        $xpGained = 0;
        if ($percentage >= 70 && ! $wasAlreadyCompleted) {
            $gapsCount = $segment->lacunas()->where('is_correct', true)->count();
            $xpGained = max(10, $gapsCount * 5);
            $user->addXp($xpGained, 'legislation', $segment->id, $segment->legislation_id);
        }

        // Próximo segmento — escopo limitado à fase se phase_block_uuids fornecido
        $nextSegment = null;
        $phaseBlockUuids = $validated['phase_block_uuids'] ?? null;

        if ($phaseBlockUuids && ! empty($phaseBlockUuids)) {
            // Buscar próximo bloco dentro da fase
            $nextSegment = LegislationSegment::whereIn('uuid', $phaseBlockUuids)
                ->where('is_block', true)
                ->where('char_start', '>', $segment->char_start)
                ->orderBy('char_start')
                ->with('lacunas')
                ->first();
        } else {
            // Comportamento legacy: próximo bloco da legislação inteira
            $legislation = $segment->legislation;
            $nextSegment = $legislation->segments()
                ->where('is_block', true)
                ->where('char_start', '>', $segment->char_start)
                ->orderBy('char_start')
                ->with('lacunas')
                ->first();
        }

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
            'user' => [
                'lives' => $user->lives,
                'xp' => $user->xp,
                'has_infinite_lives' => $user->hasInfiniteLives(),
            ],
            'xp_gained' => $xpGained,
            'lost_life' => $lostLife,
            'should_redirect' => ! $user->hasInfiniteLives() && $user->lives <= 0,
            'redirect_url' => ! $user->hasInfiniteLives() && $user->lives <= 0 ? route('play.nolives') : null,
        ]);
    }

    // ==================== PRIVATE METHODS ====================

    /**
     * Gera a lista intercalada de fases a partir das legislações selecionadas.
     */
    private function generatePhaseList($user, array $selectedUuids = []): array
    {
        // 1. Buscar legislações com seus blocos
        $query = Legislation::where('status', 'published')
            ->with(['segments' => fn ($q) => $q->where('is_block', true)->orderBy('char_start')]);

        if (! empty($selectedUuids)) {
            $query->whereIn('uuid', $selectedUuids);
        }

        $legislations = $query->orderBy('id')->get();

        if ($legislations->isEmpty()) {
            return [];
        }

        // 2. Dividir blocos em chunks por legislação
        $lawChunks = [];
        foreach ($legislations as $leg) {
            $chunks = $leg->segments->chunk(self::BLOCKS_PER_PHASE)->values();
            if ($chunks->isNotEmpty()) {
                $lawChunks[$leg->uuid] = [
                    'legislation' => $leg,
                    'chunks' => $chunks,
                    'cursor' => 0,
                ];
            }
        }

        if (empty($lawChunks)) {
            return [];
        }

        // 3. Intercalar chunks entre legislações
        $phaseList = [];
        $phaseId = 0;
        $prevLegUuid = null;

        while (true) {
            $addedAny = false;

            foreach ($lawChunks as $uuid => &$data) {
                $added = 0;

                while ($added < self::PHASES_PER_LEGISLATION_SWITCH && $data['cursor'] < $data['chunks']->count()) {
                    $phaseId++;
                    $chunk = $data['chunks'][$data['cursor']];

                    $phaseList[] = [
                        'id' => $phaseId,
                        'legislation_uuid' => $uuid,
                        'legislation_title' => $data['legislation']->title,
                        'block_ids' => $chunk->pluck('id')->toArray(),
                        'block_count' => $chunk->count(),
                        'show_legislation_header' => ($uuid !== $prevLegUuid),
                    ];

                    $prevLegUuid = $uuid;
                    $data['cursor']++;
                    $added++;
                    $addedAny = true;
                }
            }
            unset($data);

            if (! $addedAny) {
                break;
            }
        }

        // 4. Calcular progresso e bloqueio (query única)
        $allBlockIds = collect($phaseList)->pluck('block_ids')->flatten()->unique()->toArray();

        $allProgress = UserSegmentProgress::where('user_id', $user->id)
            ->whereIn('legislation_segment_id', $allBlockIds)
            ->get()
            ->keyBy('legislation_segment_id');

        $previousComplete = true;
        $currentFound = false;

        foreach ($phaseList as &$phase) {
            $completedCount = 0;
            $blockStatus = [];

            foreach ($phase['block_ids'] as $blockId) {
                $progress = $allProgress->get($blockId);

                if ($progress && $progress->is_completed) {
                    $completedCount++;
                    $blockStatus[] = ($progress->best_score >= 100) ? 'correct' : 'correct';
                } elseif ($progress && $progress->attempts > 0) {
                    $blockStatus[] = 'incorrect';
                } else {
                    $blockStatus[] = 'pending';
                }
            }

            $total = $phase['block_count'];
            $isComplete = $completedCount >= $total && $total > 0;

            $phase['is_complete'] = $isComplete;
            $phase['is_blocked'] = $currentFound || ! $previousComplete;
            $phase['is_current'] = ! $phase['is_blocked'] && ! $isComplete && ! $currentFound;

            if ($phase['is_current']) {
                $currentFound = true;
            }

            $phase['progress'] = [
                'completed' => $completedCount,
                'total' => $total,
                'percentage' => $total > 0 ? round(($completedCount / $total) * 100) : 0,
                'block_status' => $blockStatus,
            ];

            $previousComplete = $isComplete;
        }
        unset($phase);

        return $phaseList;
    }

    /**
     * Remove block_ids do payload enviado ao frontend (dados internos).
     */
    private function stripBlockIds(array $phase): array
    {
        unset($phase['block_ids']);

        return $phase;
    }

    /**
     * Formata segmento completado com dados de progresso do usuário.
     */
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

    /**
     * Formata segmento ativo para o desafio (lacunas + opções embaralhadas).
     */
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
