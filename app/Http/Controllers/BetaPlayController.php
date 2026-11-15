<?php

namespace App\Http\Controllers;

use App\Models\Legislation;
use App\Models\LegislationSegment;
use App\Models\User;
use App\Models\UserLegislationSelection;
use App\Models\UserSegmentProgress;
use App\Services\XpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Mapa beta (/beta/play): quadrados no lugar das bolas, cada quadrado com várias fases.
 *
 * Usa as mesmas tabelas do mapa atual (o progresso é por bloco, em user_segment_progress),
 * então nada aqui exige migration. A diferença é que a fase só grava no fim: quem sai no
 * meio não ganha nada e recomeça a fase.
 */
class BetaPlayController extends LegislationPlayController
{
    /** Desafios (blocos) por fase. */
    const BETA_BLOCKS_PER_PHASE = 10;

    /** Fases dentro de cada quadrado. */
    const PHASES_PER_GROUP = 4;

    /** Quadrados seguidos da mesma lei antes de trocar de lei. */
    const GROUPS_PER_SECTION = 4;

    /** Trechos enviados ao abrir o mapa: antes e depois do trecho atual. */
    const SECTIONS_BEFORE = 1;

    const SECTIONS_AFTER = 4;

    public function betaMap(Request $request)
    {
        $user = Auth::user();

        $sections = $this->buildSections($this->betaPhases($user));

        $currentIndex = 0;
        foreach ($sections as $i => $section) {
            if ($section['is_current']) {
                $currentIndex = $i;
                break;
            }
        }

        $before = self::SECTIONS_BEFORE + max(0, (int) $request->query('before', 0));
        $after = self::SECTIONS_AFTER + max(0, (int) $request->query('after', 0));

        $start = max(0, $currentIndex - $before);
        $end = min(count($sections), $currentIndex + $after + 1);

        return Inertia::render('BetaPlay/Map', [
            'sections' => array_slice($sections, $start, $end - $start),
            'hasMoreAbove' => $start > 0,
            'hasMoreBelow' => $end < count($sections),
            'window' => [
                'before' => $before - self::SECTIONS_BEFORE,
                'after' => $after - self::SECTIONS_AFTER,
            ],
            'phasesPerGroup' => self::PHASES_PER_GROUP,
        ]);
    }

    public function betaPhase(int $phaseId)
    {
        $user = Auth::user();

        if (! $user->hasLives()) {
            return redirect()->route('play.nolives');
        }

        $phases = $this->betaPhases($user);
        $phase = $this->findPlayablePhase($phases, $phaseId);

        if (! $phase) {
            return redirect()->route('beta.play.map');
        }

        $blocks = $this->phaseBlocks($phase);
        $pending = $this->blocksToPlay($user, $phase, $blocks);

        if ($pending->isEmpty()) {
            return redirect()->route('beta.play.map');
        }

        // Blocos anteriores da mesma lei, para rolar o texto para cima (como no /play)
        $legislation = Legislation::where('uuid', $phase['legislation_uuid'])->firstOrFail();
        $earlier = $legislation->segments()
            ->where('is_block', true)
            ->where('char_start', '<', $blocks->first()->char_start)
            ->orderBy('char_start', 'desc')
            ->limit(5)
            ->get()
            ->reverse()
            ->values();

        $progress = UserSegmentProgress::where('user_id', $user->id)
            ->whereIn('legislation_segment_id', $earlier->pluck('id')->merge($blocks->pluck('id')))
            ->get()
            ->keyBy('legislation_segment_id');

        $hasMoreAbove = $earlier->isNotEmpty() && $legislation->segments()
            ->where('is_block', true)
            ->where('char_start', '<', $earlier->first()->char_start)
            ->exists();

        return Inertia::render('BetaPlay/Phase', [
            'legislation' => ['uuid' => $legislation->uuid, 'title' => $legislation->title],
            'phaseId' => $phaseId,
            'phaseNumber' => $this->positionInGroup($phases, $phaseId),
            'phasesPerGroup' => self::PHASES_PER_GROUP,
            'isReview' => $phase['is_complete'],
            'challenges' => $pending->map(fn ($block) => $this->formatSegmentForChallenge($block))->values(),
            // Texto da fase inteira e o que vem antes dela, com as respostas já gravadas
            'phaseBlocks' => $blocks->map(fn ($block) => $this->formatSegmentWithProgress($block, $progress))->values(),
            'earlierBlocks' => $earlier->map(fn ($block) => $this->formatSegmentWithProgress($block, $progress))->values(),
            'hasMoreAbove' => $hasMoreAbove,
        ]);
    }

    /**
     * Erro num desafio: a vida sai na hora, mas o progresso só é gravado em betaFinish().
     */
    public function betaFail()
    {
        $user = Auth::user();

        $lostLife = false;
        if ($user->lives > 0 && ! $user->hasInfiniteLives()) {
            $user->decrementLife();
            $lostLife = true;
        }

        $outOfLives = ! $user->hasInfiniteLives() && $user->lives <= 0;

        return response()->json([
            'lost_life' => $lostLife,
            'lives' => $user->lives,
            'redirect_url' => $outOfLives ? route('play.nolives') : null,
        ]);
    }

    /**
     * Fim da fase: grava de uma vez o progresso dos blocos e credita o XP.
     */
    public function betaFinish(Request $request)
    {
        $validated = $request->validate([
            'phase_id' => 'required|integer',
            'results' => 'required|array|min:1',
            'results.*.segment_uuid' => 'required|string',
            'results.*.answers' => 'present|array',
            'results.*.answers.*.gap_order' => 'required|integer',
            'results.*.answers.*.word_position' => 'required|integer',
            'results.*.answers.*.correct_word' => 'required|string',
            'results.*.answers.*.user_word' => 'required|string',
            'results.*.answers.*.is_correct' => 'required|boolean',
            'results.*.correct_count' => 'required|integer|min:0',
            'results.*.total_count' => 'required|integer|min:0',
        ]);

        $user = Auth::user();
        $phases = $this->betaPhases($user);
        $phase = $this->findPlayablePhase($phases, (int) $validated['phase_id']);

        abort_if(! $phase, 422, 'Fase indisponível.');

        $blocks = $this->phaseBlocks($phase);
        $required = $this->blocksToPlay($user, $phase, $blocks);
        $results = collect($validated['results'])->keyBy('segment_uuid');

        // A fase só vale se todos os desafios apresentados foram vencidos
        foreach ($required as $block) {
            $result = $results->get($block->uuid);
            abort_if(
                ! $result || ! UserSegmentProgress::passed((int) $result['correct_count'], (int) $result['total_count']),
                422,
                'Fase incompleta.'
            );
        }

        $done = UserSegmentProgress::where('user_id', $user->id)
            ->whereIn('legislation_segment_id', $required->pluck('id'))
            ->where('is_completed', true)
            ->pluck('legislation_segment_id')
            ->flip();

        $legislationId = $required->first()->legislation_id;
        $xpGained = 0;

        DB::transaction(function () use ($required, $results, $done, $user, &$xpGained) {
            foreach ($required as $block) {
                $result = $results->get($block->uuid);

                UserSegmentProgress::updateProgress(
                    $user->id,
                    $block->id,
                    (int) $result['correct_count'],
                    (int) $result['total_count'],
                    $result['answers']
                );

                // XP só na primeira conclusão do bloco (mesma conta do mapa atual)
                if (! $done->has($block->id)) {
                    $gaps = $block->lacunas->where('is_correct', true)->count();
                    $xp = $user->boostedXp(max(10, $gaps * 5));
                    $user->addXp($xp, 'legislation', $block->id, $block->legislation_id);
                    $xpGained += $xp;
                }
            }
        });

        $levelUp = $xpGained > 0
            ? app(XpService::class)->detectDisciplineLevelUp($user, $legislationId, $xpGained)
            : null;

        return response()->json([
            'xp_gained' => $xpGained,
            'discipline_level_up' => $levelUp,
            'user' => [
                'lives' => $user->lives,
                'xp' => $user->xp,
                'has_infinite_lives' => $user->hasInfiniteLives(),
            ],
        ]);
    }

    // ==================== PRIVATE METHODS ====================

    private function betaPhases(User $user): array
    {
        $selectedUuids = UserLegislationSelection::where('user_id', $user->id)
            ->join('legislations', 'legislations.id', '=', 'user_legislation_selections.legislation_id')
            ->pluck('legislations.uuid')
            ->toArray();

        if (empty($selectedUuids)) {
            $selectedUuids = Legislation::where('status', 'published')->pluck('uuid')->toArray();
        }

        return $this->phaseService->generatePhaseList(
            $user,
            $selectedUuids,
            self::BETA_BLOCKS_PER_PHASE,
            self::PHASES_PER_GROUP * self::GROUPS_PER_SECTION,
        );
    }

    /**
     * Agrupa as fases em trechos (mesma lei) e cada trecho em quadrados de PHASES_PER_GROUP fases.
     */
    private function buildSections(array $phases): array
    {
        $maxPerSection = self::PHASES_PER_GROUP * self::GROUPS_PER_SECTION;

        $runs = [];
        foreach ($phases as $phase) {
            $last = count($runs) - 1;
            if (
                $last < 0
                || $runs[$last][0]['legislation_uuid'] !== $phase['legislation_uuid']
                || count($runs[$last]) >= $maxPerSection
            ) {
                $runs[] = [];
                $last++;
            }
            $runs[$last][] = $phase;
        }

        $sections = [];
        foreach ($runs as $index => $run) {
            $groups = [];
            foreach (array_chunk($run, self::PHASES_PER_GROUP) as $chunk) {
                $completed = count(array_filter($chunk, fn ($p) => $p['is_complete']));
                $current = collect($chunk)->firstWhere('is_current', true);

                $groups[] = [
                    'id' => $chunk[0]['id'],
                    'state' => $completed === count($chunk) ? 'complete' : ($current ? 'current' : 'locked'),
                    'completed_phases' => $completed,
                    'total_phases' => count($chunk),
                    'current_phase_id' => $current['id'] ?? null,
                    'phases' => array_map(fn ($p) => [
                        'id' => $p['id'],
                        'is_complete' => $p['is_complete'],
                        'is_current' => $p['is_current'],
                    ], $chunk),
                ];
            }

            $completedPhases = array_sum(array_column($groups, 'completed_phases'));

            $sections[] = [
                'number' => $index + 1,
                'legislation_title' => $run[0]['legislation_title'],
                'completed_phases' => $completedPhases,
                'total_phases' => count($run),
                'is_current' => collect($groups)->contains('state', 'current'),
                'groups' => $groups,
            ];
        }

        return $sections;
    }

    /** A fase atual ou uma já concluída (revisão); as bloqueadas não abrem. */
    private function findPlayablePhase(array $phases, int $phaseId): ?array
    {
        foreach ($phases as $phase) {
            if ($phase['id'] === $phaseId) {
                return ($phase['is_current'] || $phase['is_complete']) ? $phase : null;
            }
        }

        return null;
    }

    private function phaseBlocks(array $phase)
    {
        return LegislationSegment::whereIn('id', $phase['block_ids'])
            ->where('is_block', true)
            ->orderBy('char_start')
            ->with('lacunas')
            ->get();
    }

    /**
     * Desafios que a fase apresenta: os blocos ainda não concluídos; na revisão, todos.
     */
    private function blocksToPlay(User $user, array $phase, $blocks)
    {
        if ($phase['is_complete']) {
            return $blocks;
        }

        $done = UserSegmentProgress::where('user_id', $user->id)
            ->whereIn('legislation_segment_id', $blocks->pluck('id'))
            ->where('is_completed', true)
            ->pluck('legislation_segment_id')
            ->flip();

        return $blocks->reject(fn ($block) => $done->has($block->id))->values();
    }

    /** Posição da fase dentro do quadrado (1 a PHASES_PER_GROUP). */
    private function positionInGroup(array $phases, int $phaseId): int
    {
        foreach ($this->buildSections($phases) as $section) {
            foreach ($section['groups'] as $group) {
                foreach ($group['phases'] as $i => $phase) {
                    if ($phase['id'] === $phaseId) {
                        return $i + 1;
                    }
                }
            }
        }

        return 1;
    }
}
