<?php

namespace App\Services;

use App\Models\XpTransaction;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class StreakService
{
    /** Fuso do corte de "dia" da ofensiva (app roda em UTC). */
    public const TZ = 'America/Sao_Paulo';

    /** Rótulos PT-BR, semana iniciando na segunda-feira. */
    private const WEEKDAYS = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

    private const MONTHS = [
        'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
    ];

    /**
     * Datas (no fuso de SP) em que o usuário concluiu exercícios, com a
     * contagem por dia. Cada XpTransaction = um exercício concluído (>= 70%).
     *
     * @return array<string,int>  ['Y-m-d' => quantidade]
     */
    private function studiedDates(int $userId, ?CarbonInterface $since = null): array
    {
        $query = XpTransaction::where('user_id', $userId);

        if ($since !== null) {
            $query->where('created_at', '>=', $since->copy()->utc());
        }

        $counts = [];
        foreach ($query->orderBy('created_at')->get(['created_at']) as $row) {
            $date = $row->created_at->copy()->setTimezone(self::TZ)->toDateString();
            $counts[$date] = ($counts[$date] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Ofensiva atual: dias consecutivos terminando hoje (ou ontem, se ainda
     * não estudou hoje mas a sequência segue viva). Janela limitada para o HUD.
     */
    public function currentStreak(int $userId): int
    {
        $since = now(self::TZ)->subDays(400)->startOfDay();

        return $this->streakFromDates(array_keys($this->studiedDates($userId, $since)));
    }

    /** Maior sequência consecutiva de todo o histórico. */
    public function longestStreak(int $userId): int
    {
        return $this->longestFromDates(array_keys($this->studiedDates($userId)));
    }

    /** Concluiu ao menos um exercício hoje (fuso de SP)? */
    public function playedToday(int $userId): bool
    {
        $today = now(self::TZ)->startOfDay();

        return ! empty($this->studiedDates($userId, $today));
    }

    /**
     * Payload completo da tela de Ofensiva: ofensiva atual, recorde, hoje,
     * régua semanal e calendário mensal (heatmap).
     */
    public function getStats(int $userId, ?string $month = null): array
    {
        $counts = $this->studiedDates($userId);
        $dates = array_keys($counts);

        $today = now(self::TZ)->startOfDay();
        $todayStr = $today->toDateString();

        return [
            'current_streak' => $this->streakFromDates($dates),
            'longest_streak' => $this->longestFromDates($dates),
            'played_today' => isset($counts[$todayStr]),
            'today_count' => $counts[$todayStr] ?? 0,
            'week' => $this->buildWeek($counts, $today),
            'month' => $this->buildMonth($counts, $today, $month),
        ];
    }

    // ==================== HELPERS ====================

    /** @param array<int,string> $dates */
    private function streakFromDates(array $dates): int
    {
        $set = array_flip($dates);
        $today = now(self::TZ)->startOfDay();

        if (isset($set[$today->toDateString()])) {
            $cursor = $today->copy();
        } elseif (isset($set[$today->copy()->subDay()->toDateString()])) {
            $cursor = $today->copy()->subDay();
        } else {
            return 0;
        }

        $streak = 0;
        while (isset($set[$cursor->toDateString()])) {
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }

    /** @param array<int,string> $dates */
    private function longestFromDates(array $dates): int
    {
        if (empty($dates)) {
            return 0;
        }

        sort($dates); // 'Y-m-d' ordena cronologicamente

        $longest = 1;
        $run = 1;
        for ($i = 1, $n = count($dates); $i < $n; $i++) {
            $prevPlusOne = Carbon::parse($dates[$i - 1])->addDay()->toDateString();
            $run = ($prevPlusOne === $dates[$i]) ? $run + 1 : 1;
            $longest = max($longest, $run);
        }

        return $longest;
    }

    /** @param array<string,int> $counts */
    private function buildWeek(array $counts, CarbonInterface $today): array
    {
        $start = $today->copy()->startOfWeek(Carbon::MONDAY);

        $week = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $date = $day->toDateString();
            $count = $counts[$date] ?? 0;

            $week[] = [
                'date' => $date,
                'weekday' => self::WEEKDAYS[$i],
                'count' => $count,
                'studied' => $count > 0,
                'is_today' => $date === $today->toDateString(),
                'is_future' => $day->gt($today),
            ];
        }

        return $week;
    }

    /** @param array<string,int> $counts */
    private function buildMonth(array $counts, CarbonInterface $today, ?string $month): array
    {
        $base = now(self::TZ)->startOfMonth();
        if ($month !== null && preg_match('/^\d{4}-\d{2}$/', $month)) {
            try {
                $base = Carbon::createFromFormat('Y-m-d', $month.'-01', self::TZ)->startOfMonth();
            } catch (\Throwable $e) {
                $base = now(self::TZ)->startOfMonth();
            }
        }

        $days = [];
        for ($d = 1, $total = $base->daysInMonth; $d <= $total; $d++) {
            $day = $base->copy()->day($d);
            $date = $day->toDateString();
            $count = $counts[$date] ?? 0;

            $days[] = [
                'date' => $date,
                'day' => $d,
                'count' => $count,
                'studied' => $count > 0,
                'is_today' => $date === $today->toDateString(),
                'is_future' => $day->gt($today),
            ];
        }

        $currentMonthStart = now(self::TZ)->startOfMonth();
        $nextMonth = $base->copy()->addMonth()->startOfMonth();

        return [
            'year' => $base->year,
            'month' => $base->month,
            'label' => self::MONTHS[$base->month - 1].' '.$base->year,
            'first_weekday' => (int) $base->copy()->startOfMonth()->isoWeekday(), // 1=Seg..7=Dom
            'prev' => $base->copy()->subMonth()->format('Y-m'),
            'next' => $nextMonth->gt($currentMonthStart) ? null : $nextMonth->format('Y-m'),
            'days' => $days,
        ];
    }
}
