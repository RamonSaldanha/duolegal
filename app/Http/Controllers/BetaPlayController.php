<?php

namespace App\Http\Controllers;

use App\Models\EnergyTransaction;
use App\Models\Legislation;
use App\Models\LegislationSegment;
use App\Models\User;
use App\Models\UserBetaExam;
use App\Models\UserLegislationSelection;
use App\Models\UserSegmentProgress;
use App\Services\EnergyService;
use App\Services\StudyMaterialService;
use App\Services\XpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Mapa beta (/play): quadrados no lugar das bolas, cada quadrado com várias fases.
 *
 * Usa as mesmas tabelas do mapa atual (o progresso é por bloco, em user_segment_progress).
 * A diferença é que a fase só grava no fim: quem sai no meio não ganha nada e recomeça a fase.
 *
 * O quadrado que tem material de estudo ganha um passo a mais, o desafio (resumo e questões
 * do quadrado, em BetaCheckpointController), e o quadrado seguinte só abre depois dele. Ao
 * lado do último quadrado de cada etapa fica o Dia da prova (BetaExamController).
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

    /** Dia da prova: segundos por questão de certo/errado e de múltipla escolha, e XP por acerto. */
    const EXAM_SECONDS_TRUE_FALSE = 15;

    const EXAM_SECONDS_MULTIPLE_CHOICE = 35;

    const EXAM_XP_PER_CORRECT = 15;

    public function betaMap(Request $request)
    {
        return Inertia::render('BetaPlay/Map', $this->mapPayload(Auth::user(), $request));
    }

    public function betaPhase(int $phaseId)
    {
        $payload = $this->phasePayload(Auth::user(), $phaseId);

        return match ($payload) {
            'no_energy' => redirect()->route('beta.play.energy'),
            'unavailable' => redirect()->route('play.map'),
            default => Inertia::render('BetaPlay/Phase', $payload),
        };
    }

    /** Sem energia: a barra, o anúncio e o convite Premium. */
    public function betaEnergy()
    {
        return Inertia::render('BetaPlay/NoEnergy', $this->energyStatus(Auth::user()));
    }

    /**
     * Anúncio assistido até o fim: um tanto de energia, até a barra cheia, e no máximo
     * `ad_daily` por dia. Depois disso, 429 com o código `ad_limit`.
     */
    public function betaEnergyReward()
    {
        $user = Auth::user();
        if ($this->adStatus($user)['ads_left'] <= 0) {
            return response()->json(['message' => 'Os anúncios de hoje acabaram. Volte amanhã.', 'code' => 'ad_limit'], 429);
        }

        $energy = app(EnergyService::class);
        $gained = $energy->apply($user, (int) config('energy.ad_reward'), EnergyTransaction::AD);

        return response()->json(['success' => true, 'gained' => $gained, 'energy' => $energy->payload($user)] + $this->adStatus($user));
    }

    /**
     * A tela sem energia: a energia (com a hora do próximo +1), quanto o anúncio dá, quantos
     * ainda valem hoje e qual vem agora.
     *
     * @return array<string, mixed>
     */
    protected function energyStatus(User $user): array
    {
        return [
            'energy' => app(EnergyService::class)->payload($user),
            'ad_reward' => (int) config('energy.ad_reward'),
        ] + $this->adStatus($user);
    }

    /**
     * Os anúncios de hoje (dia de Brasília) e o próximo: o nosso (o vídeo do Premium) e o do
     * Google se revezam, começando pelo nosso. `seconds` é quanto o do Google conta antes de
     * dar a energia; o nosso dá quando o vídeo acaba.
     *
     * @return array{ads_left: int, ad: array{kind: string, video_url: string|null, seconds: int}}
     */
    protected function adStatus(User $user): array
    {
        $ads = EnergyTransaction::where('user_id', $user->id)->where('reason', EnergyTransaction::AD);
        $dayStart = now(config('energy.ad_timezone'))->startOfDay()->setTimezone(config('app.timezone'));
        $today = (clone $ads)->where('created_at', '>=', $dayStart)->count();
        $house = $ads->count() % 2 === 0;

        return [
            'ads_left' => max(0, (int) config('energy.ad_daily') - $today),
            'ad' => [
                'kind' => $house ? 'house' : 'google',
                'video_url' => $house ? asset(config('energy.ad_video')) : null,
                'seconds' => (int) config('energy.ad_google_seconds'),
            ],
        ];
    }

    /**
     * O mapa: as etapas em volta da atual (mais `before`/`after` etapas, quando a tela pede).
     * A web e a API do app recebem os mesmos dados.
     *
     * @return array<string, mixed>
     */
    protected function mapPayload(User $user, Request $request): array
    {
        $sections = $this->forScreen($this->trail($user)['sections']);

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

        return [
            'sections' => array_slice($sections, $start, $end - $start),
            'hasMoreAbove' => $start > 0,
            'hasMoreBelow' => $end < count($sections),
            'window' => [
                'before' => $before - self::SECTIONS_BEFORE,
                'after' => $after - self::SECTIONS_AFTER,
            ],
        ];
    }

    /**
     * Os dados da fase, ou por que ela não abre: 'no_energy' (sem a energia mínima; a revisão
     * pede menos) ou 'unavailable' (bloqueada, inexistente ou sem desafio a jogar). A web e a
     * API recebem os mesmos dados.
     *
     * @return array<string, mixed>|'no_energy'|'unavailable'
     */
    protected function phasePayload(User $user, int $phaseId): array|string
    {
        $trail = $this->trail($user);
        $phase = $this->findPlayablePhase($trail['phases'], $phaseId);

        if (! $phase) {
            return 'unavailable';
        }

        [$stepNumber, $stepCount] = $this->positionInGroup($trail['sections'], $phaseId);

        $blocks = $this->phaseBlocks($phase);
        $pending = $this->blocksToPlay($user, $phase, $blocks);

        if ($pending->isEmpty()) {
            return 'unavailable';
        }

        // O mínimo para abrir: na fase nova, o de config ou, se ela tem poucos blocos, o custo de
        // errar cada um uma vez; na revisão, o de config
        $energy = app(EnergyService::class);
        $need = $phase['is_complete']
            ? (int) config('energy.review.open')
            : min((int) config('energy.phase.open'), $pending->count() * (int) config('energy.phase.wrong'));
        if (! $energy->has($user, $need)) {
            return 'no_energy';
        }

        $energy->startRun($user, $this->newRun($phase, $pending->count()));

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

        return [
            'legislation' => ['uuid' => $legislation->uuid, 'title' => $legislation->title],
            'phaseId' => $phaseId,
            // Passo dentro do quadrado; o desafio, quando o quadrado tem material, é o último
            'phaseNumber' => $stepNumber,
            'phasesPerGroup' => $stepCount,
            'isReview' => $phase['is_complete'],
            'challenges' => $pending->map(fn ($block) => $this->formatSegmentForChallenge($block))->values(),
            // Texto da fase inteira e o que vem antes dela, com as respostas já gravadas
            'phaseBlocks' => $blocks->map(fn ($block) => $this->formatSegmentWithProgress($block, $progress))->values(),
            'earlierBlocks' => $earlier->map(fn ($block) => $this->formatSegmentWithProgress($block, $progress))->values(),
            'hasMoreAbove' => $hasMoreAbove,
            'energy' => $energy->payload($user),
        ];
    }

    /**
     * Cada bloco verificado na fase, certo ou errado: cobra a energia na hora, conta a
     * sequência para o combo e diz se a energia acabou no meio (aí a pessoa sai e perde a
     * fase). O progresso continua sendo gravado só em betaFinish().
     */
    public function betaAttempt(Request $request)
    {
        $validated = $request->validate([
            'phase_id' => 'required|integer',
            'correct' => 'required|boolean',
        ]);

        $user = Auth::user();
        $run = $this->runFor($user, (int) $validated['phase_id']);

        abort_if(! $run, 422, 'Fase indisponível.');

        return response()->json(app(EnergyService::class)->phaseAttempt($user, $run, (bool) $validated['correct']));
    }

    /**
     * Erro num bloco, dos builds do app anteriores à energia (que não mandam a fase): conta
     * como tentativa errada da fase em jogo. As vidas não mudam mais aqui.
     */
    public function betaFail()
    {
        $user = Auth::user();
        $run = $user->energy_run;

        if (($run['kind'] ?? null) === 'phase') {
            app(EnergyService::class)->phaseAttempt($user, $run, false);
        }

        return response()->json([
            'lost_life' => false,
            'lives' => $user->lives,
            'redirect_url' => null,
            'out_of_lives' => false,
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
        $phase = $this->findPlayablePhase($this->trail($user)['phases'], (int) $validated['phase_id']);

        abort_if(! $phase, 422, 'Fase indisponível.');

        // A energia acabou no meio da fase: ela foi perdida
        $run = $user->energy_run;
        abort_if(
            ($run['kind'] ?? null) === 'phase' && ($run['id'] ?? null) === $phase['id'] && ! empty($run['out']),
            409,
            'A energia acabou no meio da fase.'
        );

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

        $phaseId = $phase['id'];

        DB::transaction(function () use ($required, $results, $done, $user, $run, $phaseId, &$xpGained) {
            foreach ($required as $block) {
                $result = $results->get($block->uuid);

                UserSegmentProgress::updateProgress(
                    $user->id,
                    $block->id,
                    (int) $result['correct_count'],
                    (int) $result['total_count'],
                    $result['answers']
                );

                // XP só na primeira conclusão do bloco (mesma conta do mapa atual). O bloco
                // vetado ou revogado é jogado normalmente, mas não tem texto e não dá XP.
                if (! $done->has($block->id) && $block->historicalLabel() === null) {
                    $gaps = $block->lacunas->where('is_correct', true)->count();
                    $xp = $user->boostedXp(max(10, $gaps * 5));
                    $user->addXp($xp, 'legislation', $block->id, $block->legislation_id);
                    $xpGained += $xp;
                }
            }

            // Os combos de quem assina viram XP aqui, só quando a fase deu XP (primeira conclusão)
            if ($xpGained > 0 && ($run['kind'] ?? null) === 'phase' && ($run['id'] ?? null) === $phaseId) {
                $comboXp = app(EnergyService::class)->phaseComboXp($user, $run);
                if ($comboXp > 0) {
                    $user->addXp($comboXp, EnergyService::COMBO_XP_SOURCE, $phaseId, $required->first()->legislation_id);
                    $xpGained += $comboXp;
                }
            }
        });

        $levelUp = $xpGained > 0
            ? app(XpService::class)->detectDisciplineLevelUp($user, $legislationId, $xpGained)
            : null;

        $energy = app(EnergyService::class);
        $energy->clearRun($user);

        return response()->json([
            'xp_gained' => $xpGained,
            'discipline_level_up' => $levelUp,
            'user' => [
                'lives' => $user->lives,
                'xp' => $user->xp,
                'has_infinite_lives' => $user->hasInfiniteLives(),
                'energy' => $energy->payload($user),
            ],
        ]);
    }

    /**
     * A fase em jogo para a energia. Se a fase pedida não é a que foi aberta por último (outra
     * aba, por exemplo), começa uma do zero a partir da trilha.
     */
    private function runFor(User $user, int $phaseId): ?array
    {
        $run = $user->energy_run;
        if (($run['kind'] ?? null) === 'phase' && ($run['id'] ?? null) === $phaseId) {
            return $run;
        }

        $phase = $this->findPlayablePhase($this->trail($user)['phases'], $phaseId);
        if (! $phase) {
            return null;
        }

        $pending = $this->blocksToPlay($user, $phase, $this->phaseBlocks($phase))->count();
        $run = $this->newRun($phase, $pending);
        app(EnergyService::class)->startRun($user, $run);

        return $run;
    }

    /** @return array{kind: string, id: int, review: bool, pending: int, corrects: int, streak: int, combos: int, out: bool} */
    private function newRun(array $phase, int $pending): array
    {
        return [
            'kind' => 'phase',
            'id' => $phase['id'],
            'review' => (bool) $phase['is_complete'],
            'pending' => $pending,
            'corrects' => 0,
            'streak' => 0,
            'combos' => 0,
            'out' => false,
        ];
    }

    // ==================== PRIVATE METHODS ====================

    protected function betaPhases(User $user): array
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
     * A trilha inteira do usuário: as fases e as etapas (mesma lei), cada etapa com os seus
     * quadrados de PHASES_PER_GROUP fases, o desafio de cada quadrado e o Dia da prova.
     *
     * O desafio é o último passo do quadrado que tem material. Ele trava só a passagem para o
     * quadrado seguinte: se a próxima fase a jogar é a primeira de um quadrado e o desafio do
     * quadrado anterior está por fazer, a fase fica bloqueada e o passo atual é o desafio.
     * Quem já passou adiante (material publicado depois) não é puxado de volta: o desafio
     * fica aberto no quadrado concluído.
     *
     * @return array{phases: array<int, array<string, mixed>>, sections: array<int, array<string, mixed>>}
     */
    protected function trail(User $user): array
    {
        $phases = $this->betaPhases($user);
        $maxPerSection = self::PHASES_PER_GROUP * self::GROUPS_PER_SECTION;

        // Etapas: os índices das fases seguidas da mesma lei
        $runs = [];
        foreach ($phases as $index => $phase) {
            $last = count($runs) - 1;
            if (
                $last < 0
                || $phases[$runs[$last][0]]['legislation_uuid'] !== $phase['legislation_uuid']
                || count($runs[$last]) >= $maxPerSection
            ) {
                $runs[] = [];
                $last++;
            }
            $runs[$last][] = $index;
        }

        // Quadrados na ordem da trilha: os índices das fases de cada um
        $squares = [];
        $squaresOfRun = [];
        foreach ($runs as $ri => $run) {
            foreach (array_chunk($run, self::PHASES_PER_GROUP) as $chunk) {
                $squaresOfRun[$ri][] = count($squares);
                $squares[] = $chunk;
            }
        }

        $challenges = app(StudyMaterialService::class)->squareChallenges($user, array_map(
            fn (array $chunk) => array_merge(...array_map(fn (int $index) => $phases[$index]['block_ids'], $chunk)),
            $squares,
        ));
        $pending = fn (int $square) => $challenges[$square]['completed'] < $challenges[$square]['total'];

        // O desafio que trava o avanço: o do quadrado logo antes da próxima fase a jogar,
        // quando ela abre um quadrado novo (ou quando a trilha acabou)
        $currentIndex = array_key_first(array_filter($phases, fn ($phase) => $phase['is_current']));
        $finished = $phases !== [] && $currentIndex === null && end($phases)['is_complete'];
        $blocking = null;
        foreach ($squares as $square => $chunk) {
            $next = $squares[$square + 1][0] ?? null;
            if (($next !== null ? $next === $currentIndex : $finished) && $pending($square)) {
                $blocking = $square;
            }
        }

        if ($blocking !== null && $currentIndex !== null) {
            $phases[$currentIndex]['is_current'] = false;
            $phases[$currentIndex]['is_blocked'] = true;
        }

        // A etapa é identificada pelo último bloco dela, que é o do último quadrado
        $anchors = [];
        foreach ($squaresOfRun as $ri => $own) {
            $anchors[$ri] = $challenges[end($own)]['anchor_block_id'];
        }
        $exams = UserBetaExam::where('user_id', $user->id)
            ->whereIn('legislation_segment_id', array_values(array_filter($anchors)))
            ->get()
            ->keyBy('legislation_segment_id');

        $sections = [];
        foreach ($runs as $ri => $run) {
            $groups = [];
            foreach ($squaresOfRun[$ri] as $square) {
                $chunk = array_map(fn (int $index) => $phases[$index], $squares[$square]);
                $completed = count(array_filter($chunk, fn ($p) => $p['is_complete']));
                $current = collect($chunk)->firstWhere('is_current', true);
                $phasesDone = $completed === count($chunk);

                $has = $challenges[$square]['total'] > 0;
                $challengeDone = $has && ! $pending($square);

                $groups[] = [
                    'id' => $chunk[0]['id'],
                    'state' => $current || $square === $blocking ? 'current' : ($phasesDone ? 'complete' : 'locked'),
                    'completed_phases' => $completed,
                    'total_phases' => count($chunk),
                    // Passos do quadrado: as fases e, quando há material, o desafio
                    'completed_steps' => $completed + ($challengeDone ? 1 : 0),
                    'total_steps' => count($chunk) + ($has ? 1 : 0),
                    'current_phase_id' => $current['id'] ?? null,
                    'phases' => array_map(fn ($p) => [
                        'id' => $p['id'],
                        'is_complete' => $p['is_complete'],
                        'is_current' => $p['is_current'],
                    ], $chunk),
                    'challenge' => [
                        // none: quadrado sem material · locked: faltam fases · available · complete
                        'state' => match (true) {
                            ! $has => 'none',
                            ! $phasesDone => 'locked',
                            $challengeDone => 'complete',
                            default => 'available',
                        },
                        // É o passo atual da trilha: o quadrado seguinte espera por ele
                        'is_current' => $square === $blocking,
                    ] + $challenges[$square],
                ];
            }

            $sections[] = [
                'number' => $ri + 1,
                'legislation_title' => $phases[$run[0]]['legislation_title'],
                'completed_phases' => array_sum(array_column($groups, 'completed_phases')),
                'total_phases' => count($run),
                'completed_steps' => array_sum(array_column($groups, 'completed_steps')),
                'total_steps' => array_sum(array_column($groups, 'total_steps')),
                'is_current' => collect($groups)->contains('state', 'current'),
                'groups' => $groups,
                'exam' => $this->examOf($groups, $anchors[$ri], $exams->get($anchors[$ri])),
            ];
        }

        return ['phases' => $phases, 'sections' => $sections];
    }

    /**
     * Dia da prova da etapa: só as questões dos desafios dos quadrados dela, com tempo. Libera
     * quando todas as fases e todos os desafios da etapa estão feitos. Fica fora do avanço da
     * trilha, não gasta energia e rende energia (BetaExamController).
     */
    private function examOf(array $groups, ?int $anchor, ?UserBetaExam $exam): array
    {
        $challenges = array_column($groups, 'challenge');
        $questions = array_sum(array_column($challenges, 'questions'));
        $trueFalse = array_sum(array_column($challenges, 'true_false_questions'));

        $done = collect($groups)->every(fn ($group) => $group['completed_steps'] === $group['total_steps']);

        return [
            'key' => $challenges ? end($challenges)['key'] : null,
            // locked: falta concluir a etapa · empty: sem questões · available · complete: já entregou uma prova
            'state' => match (true) {
                ! $done => 'locked',
                $questions === 0 => 'empty',
                $exam !== null && $exam->attempts > 0 => 'complete',
                default => 'available',
            },
            'questions' => $questions,
            'seconds' => $trueFalse * self::EXAM_SECONDS_TRUE_FALSE + ($questions - $trueFalse) * self::EXAM_SECONDS_MULTIPLE_CHOICE,
            'xp_per_correct' => self::EXAM_XP_PER_CORRECT,
            // Refazer custa energia ao começar
            'retake_energy' => (int) config('energy.exam.retake'),
            'best_correct' => min($questions, (int) ($exam?->best_correct ?? 0)),
            'in_progress' => (bool) $exam?->isRunning(),
            // Só para uso interno; saem antes de ir para a tela
            'anchor_block_id' => $anchor,
            'material_ids' => array_merge([], ...array_column($challenges, 'material_ids')),
        ];
    }

    /** Tira das etapas o que é só de uso interno, antes de mandar para a tela. */
    private function forScreen(array $sections): array
    {
        $internal = ['anchor_block_id' => true, 'material_ids' => true, 'true_false_questions' => true];

        foreach ($sections as &$section) {
            $section['exam'] = array_diff_key($section['exam'], $internal);
            foreach ($section['groups'] as &$group) {
                $group['challenge'] = array_diff_key($group['challenge'], $internal);
            }
            unset($group);
        }
        unset($section);

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

    /**
     * Posição da fase dentro do quadrado e quantos passos ele tem.
     *
     * @return array{0: int, 1: int}
     */
    private function positionInGroup(array $sections, int $phaseId): array
    {
        foreach ($sections as $section) {
            foreach ($section['groups'] as $group) {
                foreach ($group['phases'] as $i => $phase) {
                    if ($phase['id'] === $phaseId) {
                        return [$i + 1, $group['total_steps']];
                    }
                }
            }
        }

        return [1, self::PHASES_PER_GROUP];
    }
}
