<?php

namespace App\Services;

use App\Models\User;
use App\Models\XpTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class XpService
{
    public function awardXp(User $user, int $amount, string $sourceType, ?int $sourceId = null, ?int $legislationId = null): XpTransaction
    {
        return $user->addXp($amount, $sourceType, $sourceId, $legislationId);
    }

    public function getUserTotalXp(int $userId): int
    {
        return (int) XpTransaction::where('user_id', $userId)->sum('amount');
    }

    public function getRanking(string $period = 'all', int $limit = 20): Collection
    {
        $query = DB::table('xp_transactions')
            ->join('users', 'users.id', '=', 'xp_transactions.user_id')
            ->select('users.id', 'users.name', DB::raw('SUM(xp_transactions.amount) as total_xp'));

        $this->applyPeriodFilter($query, $period);

        return $query
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_xp')
            ->limit($limit)
            ->get();
    }

    public function getUserPositionInRanking(int $userId, string $period = 'all'): ?int
    {
        $userXp = $this->getUserXpForPeriod($userId, $period);

        if ($userXp === 0) {
            return null;
        }

        $query = DB::table('xp_transactions')
            ->select('user_id', DB::raw('SUM(amount) as total_xp'));

        $this->applyPeriodFilter($query, $period);

        $position = $query
            ->groupBy('user_id')
            ->having('total_xp', '>', $userXp)
            ->count();

        return $position + 1;
    }

    public function getUserXpForPeriod(int $userId, string $period): int
    {
        $query = XpTransaction::where('user_id', $userId);

        if ($period === 'daily') {
            $query->today();
        } elseif ($period === 'weekly') {
            $query->thisWeek();
        }

        return (int) $query->sum('amount');
    }

    public function getUserDisciplineXp(int $userId): Collection
    {
        return DB::table('xp_transactions')
            ->join('discipline_legislation', 'discipline_legislation.legislation_id', '=', 'xp_transactions.legislation_id')
            ->join('disciplines', 'disciplines.id', '=', 'discipline_legislation.discipline_id')
            ->where('xp_transactions.user_id', $userId)
            ->whereNotNull('xp_transactions.legislation_id')
            ->select(
                'disciplines.id',
                'disciplines.uuid',
                'disciplines.name',
                'disciplines.slug',
                'disciplines.description',
                'disciplines.icon',
                'disciplines.color',
                DB::raw('SUM(xp_transactions.amount) as total_xp')
            )
            ->groupBy('disciplines.id', 'disciplines.uuid', 'disciplines.name', 'disciplines.slug', 'disciplines.description', 'disciplines.icon', 'disciplines.color')
            ->orderByDesc('total_xp')
            ->get()
            ->map(function ($discipline) {
                $levelData = $this->calculateLevel((int) $discipline->total_xp);

                return (object) array_merge((array) $discipline, $levelData);
            });
    }

    public function calculateLevel(int $xp): array
    {
        $level = 1;
        $totalNeeded = 0;

        while (true) {
            $needed = (int) floor(100 * pow(1.5, $level - 1));

            if ($totalNeeded + $needed > $xp) {
                return [
                    'level' => $level,
                    'current_xp_in_level' => $xp - $totalNeeded,
                    'xp_for_next_level' => $needed,
                    'progress_percent' => $needed > 0 ? round(($xp - $totalNeeded) / $needed * 100) : 0,
                    'total_xp' => $xp,
                ];
            }

            $totalNeeded += $needed;
            $level++;
        }
    }

    /**
     * Detecta se a(s) disciplina(s) ligada(s) a esta legislação subiram de nível
     * por causa do XP recém-creditado. Deve ser chamado DEPOIS de $user->addXp(...),
     * pois compara o total atual (que já inclui o ganho) com o total anterior.
     *
     * Retorna null se não houve level-up, ou um array com os dados da disciplina
     * para o frontend renderizar a celebração (badge + nível).
     */
    public function detectDisciplineLevelUp(User $user, ?int $legislationId, int $xpGained): ?array
    {
        if ($xpGained <= 0 || $legislationId === null) {
            return null;
        }

        $disciplines = DB::table('discipline_legislation')
            ->join('disciplines', 'disciplines.id', '=', 'discipline_legislation.discipline_id')
            ->where('discipline_legislation.legislation_id', $legislationId)
            ->select('disciplines.id', 'disciplines.name', 'disciplines.icon', 'disciplines.color')
            ->get();

        foreach ($disciplines as $d) {
            // Total atual da disciplina (já inclui o XP recém-creditado).
            $newTotal = (int) DB::table('xp_transactions')
                ->join('discipline_legislation', 'discipline_legislation.legislation_id', '=', 'xp_transactions.legislation_id')
                ->where('discipline_legislation.discipline_id', $d->id)
                ->where('xp_transactions.user_id', $user->id)
                ->sum('xp_transactions.amount');

            $oldLevel = $this->calculateLevel(max(0, $newTotal - $xpGained))['level'];
            $newLevel = $this->calculateLevel($newTotal)['level'];

            if ($newLevel > $oldLevel) {
                return [
                    'discipline_id' => $d->id,
                    'discipline_name' => $d->name,
                    'icon' => $d->icon,
                    'color' => $d->color,
                    'old_level' => $oldLevel,
                    'new_level' => $newLevel,
                ];
            }
        }

        return null;
    }

    private function applyPeriodFilter($query, string $period): void
    {
        if ($period === 'daily') {
            $query->whereDate('xp_transactions.created_at', today());
        } elseif ($period === 'weekly') {
            $query->whereBetween('xp_transactions.created_at', [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ]);
        }
    }
}
