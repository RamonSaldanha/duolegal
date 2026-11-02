<?php

namespace App\Services;

use App\Models\Discipline;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Estrelas das conquistas (0 a 5 por disciplina), calculadas a partir do XP.
 *
 * Cada disciplina tem metas próprias, proporcionais à raiz do XP que ela oferece:
 * matérias grandes exigem mais XP, mas uma fração menor do total. As metas ficam
 * salvas em disciplines.star_thresholds, então cadastrar uma lei não as altera, e
 * user_discipline_stars guarda o maior valor já conquistado: estrela não se perde.
 */
class DisciplineStarService
{
    public const MAX_STARS = 5;

    private const FIVE_STAR_FACTOR = 72.52;

    /** Parte da meta de 5 estrelas exigida para 2, 3, 4 e 5 estrelas. */
    private const STAR_SHARES = [0.03, 0.15, 0.45, 1.0];

    /** Faixa usada só para exibição em disciplina que ainda não oferece XP. */
    private const FALLBACK_BAND = 100;

    /**
     * XP que cada disciplina oferece hoje, pela mesma fórmula do jogo (ver PlayController).
     *
     * @return array<int, int> discipline_id => XP
     */
    public function potentials(?int $disciplineId = null): array
    {
        $blocks = DB::table('legislation_segments as s')
            ->join('legislations as l', 'l.id', '=', 's.legislation_id')
            ->join('discipline_legislation as dl', 'dl.legislation_id', '=', 's.legislation_id')
            ->leftJoin('segment_lacunas as g', function ($join) {
                $join->on('g.legislation_segment_id', '=', 's.id')->where('g.is_correct', true);
            })
            ->where('l.status', 'published')
            ->where('s.is_block', true)
            ->when($disciplineId, fn ($query) => $query->where('dl.discipline_id', $disciplineId))
            ->selectRaw('dl.discipline_id, s.id, COUNT(g.id) as gaps')
            ->groupBy('dl.discipline_id', 's.id')
            ->get();

        $potentials = [];
        foreach ($blocks as $block) {
            $potentials[$block->discipline_id] = ($potentials[$block->discipline_id] ?? 0) + max(10, $block->gaps * 5);
        }

        return $potentials;
    }

    /**
     * @return array<int, int> XP mínimo para 2, 3, 4 e 5 estrelas (vazio se a disciplina não oferece XP)
     */
    public function thresholdsFromPotential(int $potential): array
    {
        if ($potential <= 0) {
            return [];
        }

        $fiveStars = self::FIVE_STAR_FACTOR * sqrt($potential);

        return array_map(fn (float $share) => (int) round($fiveStars * $share), self::STAR_SHARES);
    }

    /** @param array<int, int> $thresholds */
    public function starsForXp(int $xp, array $thresholds): int
    {
        if ($xp <= 0) {
            return 0;
        }

        $stars = 1;
        foreach ($thresholds as $index => $minimum) {
            if ($xp >= $minimum) {
                $stars = $index + 2;
            }
        }

        return $stars;
    }

    /** Progresso do usuário em todas as disciplinas, em ordem de nome. */
    public function progressFor(User $user): Collection
    {
        $xp = $this->userDisciplineXp($user->id);
        $marks = $this->marks($user->id);
        $newMarks = [];

        $progress = Discipline::orderBy('name')->get()->map(function (Discipline $discipline) use ($xp, $marks, &$newMarks) {
            $totalXp = $xp[$discipline->id] ?? 0;
            $thresholds = $this->thresholdsFor($discipline);
            $earned = $this->starsForXp($totalXp, $thresholds);
            $mark = $marks[$discipline->id] ?? 0;

            if ($earned > $mark) {
                $newMarks[$discipline->id] = $earned;
            }

            return $this->formatProgress($discipline, $totalXp, max($earned, $mark), $thresholds);
        });

        $this->saveMarks($user->id, $newMarks);

        return $progress->values();
    }

    /** Disciplinas com estrela: mais estrelas primeiro, depois mais XP, como na página Conquistas. */
    public function earnedFor(User $user): Collection
    {
        return $this->progressFor($user)
            ->filter(fn (array $progress) => $progress['stars'] > 0)
            ->sortBy([['stars', 'desc'], ['total_xp', 'desc']])
            ->values();
    }

    /**
     * Registra as estrelas ganhas com o XP recém-creditado (já somado ao total) e devolve os
     * dados da celebração, ou null se nenhuma disciplina ganhou estrela.
     */
    public function detectStarGain(User $user, ?int $legislationId, int $xpGained): ?array
    {
        if ($xpGained <= 0 || $legislationId === null) {
            return null;
        }

        $disciplines = Discipline::whereHas('legislations', fn ($query) => $query->where('legislations.id', $legislationId))->get();
        if ($disciplines->isEmpty()) {
            return null;
        }

        $xp = $this->userDisciplineXp($user->id);
        $marks = $this->marks($user->id);
        $newMarks = [];
        $gain = null;

        foreach ($disciplines as $discipline) {
            $thresholds = $this->thresholdsFor($discipline);
            $total = $xp[$discipline->id] ?? 0;
            $mark = $marks[$discipline->id] ?? 0;

            $before = max($mark, $this->starsForXp(max(0, $total - $xpGained), $thresholds));
            $after = max($mark, $this->starsForXp($total, $thresholds));

            if ($after > $mark) {
                $newMarks[$discipline->id] = $after;
            }

            if ($after > $before && $gain === null) {
                $gain = [
                    'discipline_id' => $discipline->id,
                    'discipline_name' => $discipline->name,
                    'icon' => $discipline->icon,
                    'color' => $discipline->color,
                    'old_level' => $before,
                    'new_level' => $after,
                    'stars' => $after,
                    'badge_url' => $this->badgeUrl($discipline, $after),
                ];
            }
        }

        $this->saveMarks($user->id, $newMarks);

        return $gain;
    }

    /** @return array<int, int> discipline_id => XP do usuário, com o crédito do XP antigo */
    private function userDisciplineXp(int $userId): array
    {
        $xp = DB::table('xp_transactions')
            ->join('discipline_legislation', 'discipline_legislation.legislation_id', '=', 'xp_transactions.legislation_id')
            ->where('xp_transactions.user_id', $userId)
            ->selectRaw('discipline_legislation.discipline_id, SUM(xp_transactions.amount) as total')
            ->groupBy('discipline_legislation.discipline_id')
            ->pluck('total', 'discipline_id')
            ->map(fn ($total) => (int) $total)
            ->all();

        $credits = DB::table('discipline_xp_credits')->where('user_id', $userId)->pluck('xp', 'discipline_id');
        foreach ($credits as $disciplineId => $credit) {
            $xp[$disciplineId] = ($xp[$disciplineId] ?? 0) + (int) $credit;
        }

        return $xp;
    }

    /** Metas salvas ou, enquanto a disciplina não foi fixada, as calculadas na hora. */
    private function thresholdsFor(Discipline $discipline): array
    {
        return $discipline->star_thresholds
            ?? $this->thresholdsFromPotential($this->potentials($discipline->id)[$discipline->id] ?? 0);
    }

    /** @return array<int, int> discipline_id => estrelas */
    private function marks(int $userId): array
    {
        return DB::table('user_discipline_stars')
            ->where('user_id', $userId)
            ->pluck('stars', 'discipline_id')
            ->map(fn ($stars) => (int) $stars)
            ->all();
    }

    /** @param array<int, int> $marks discipline_id => estrelas */
    private function saveMarks(int $userId, array $marks): void
    {
        if ($marks === []) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($marks as $disciplineId => $stars) {
            $rows[] = ['user_id' => $userId, 'discipline_id' => $disciplineId, 'stars' => $stars, 'created_at' => $now, 'updated_at' => $now];
        }

        DB::table('user_discipline_stars')->upsert($rows, ['user_id', 'discipline_id'], ['stars', 'updated_at']);
    }

    /**
     * Os campos de nível continuam na resposta porque o app já instalado os usa para
     * desenhar o badge antigo: level vira as estrelas e a barra mede a faixa atual.
     */
    private function formatProgress(Discipline $discipline, int $totalXp, int $stars, array $thresholds): array
    {
        $bandStart = $stars >= 2 ? ($thresholds[$stars - 2] ?? 0) : 0;
        $nextStarXp = $stars < self::MAX_STARS ? ($thresholds[max(0, $stars - 1)] ?? null) : null;
        $previousBandStart = $stars >= 3 ? ($thresholds[$stars - 3] ?? 0) : 0;
        $band = $nextStarXp !== null ? $nextStarXp - $bandStart : $bandStart - $previousBandStart;
        $band = max($band, $thresholds === [] ? self::FALLBACK_BAND : 1);
        $inBand = $nextStarXp === null ? $band : min(max(0, $totalXp - $bandStart), $band);

        return [
            'id' => $discipline->id,
            'uuid' => $discipline->uuid,
            'name' => $discipline->name,
            'slug' => $discipline->slug,
            'description' => $discipline->description,
            'icon' => $discipline->icon,
            'color' => $discipline->color,
            'total_xp' => $totalXp,
            'level' => max(1, $stars),
            'current_xp_in_level' => $inBand,
            'xp_for_next_level' => $band,
            'progress_percent' => (int) round($inBand / $band * 100),
            'stars' => $stars,
            'next_star_xp' => $nextStarXp,
            'badge_url' => $this->badgeUrl($discipline, $stars),
        ];
    }

    /** Imagem gerada no admin ou arte original; sem nenhuma das duas, a tela usa o badge antigo. */
    public function badgeUrl(Discipline $discipline, int $stars): ?string
    {
        if ($discipline->badge_version) {
            return route('disciplines.badge', ['discipline' => $discipline, 'stars' => $stars, 'v' => $discipline->badge_version]);
        }

        return $discipline->badge_key ? asset("img/badges/{$discipline->badge_key}-{$stars}.png") : null;
    }

    /** Fixa as metas assim que a disciplina oferece XP. Metas já fixadas não mudam. */
    public function freezeThresholds(Discipline $discipline): void
    {
        if ($discipline->star_thresholds !== null) {
            return;
        }

        $thresholds = $this->thresholdsFromPotential($this->potentials($discipline->id)[$discipline->id] ?? 0);

        if ($thresholds !== []) {
            $discipline->update(['star_thresholds' => $thresholds]);
        }
    }
}
