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
