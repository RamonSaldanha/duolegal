<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ReengagementMail;
use App\Models\ReengagementEmail;
use App\Models\User;
use App\Models\UserLegislationSelection;
use App\Services\ReengagementService;
use App\Services\StreakService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Subscription;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    /** Períodos do gráfico de crescimento, em dias. */
    private const PERIODS = [7, 30, 90];

    /**
     * Display a listing of all users.
     */
    public function index(Request $request): Response
    {
        $days = in_array((int) $request->query('days'), self::PERIODS, true) ? (int) $request->query('days') : 30;
        $activeSubscriptionUserIds = $this->activeSubscriptionUserIds();

        $users = User::query()
            ->withLastPlayed()
            ->withSum('xpTransactions as xp_total', 'amount')
            ->withCount([
                'reengagementEmails as emails_sent',
                'reengagementEmails as gifts_claimed' => fn ($query) => $query->whereNotNull('claimed_at'),
            ])
            ->with('subscriptions')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (User $user) use ($activeSubscriptionUserIds) {
                $answers = $user->onboarding_answers ?? [];

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_config' => $user->publicAvatarConfig(),
                    'lives' => $user->lives,
                    'xp' => (int) $user->xp_total,
                    'is_admin' => $user->is_admin,
                    'is_premium' => $activeSubscriptionUserIds->contains($user->id),
                    'goal' => $answers['goal'] ?? null,
                    'area' => $answers['area'] ?? null,
                    'minutes' => $answers['minutes'] ?? null,
                    'receives_emails' => $user->receivesEmails(),
                    'emails_sent' => $user->emails_sent,
                    'gifts_claimed' => $user->gifts_claimed,
                    'last_played_at' => $user->lastPlayedAt()?->toIso8601String(),
                    'created_at' => $user->created_at->toIso8601String(),
                ];
            });

        $since30 = now()->subDays(30);

        return Inertia::render('admin/Users/Index', [
            'users' => $users,
            'stats' => [
                'total_users' => $users->count(),
                'paying_users' => $activeSubscriptionUserIds->count(),
                'active_7d' => $users->filter(fn ($u) => $u['last_played_at'] && Carbon::parse($u['last_played_at'])->gt(now()->subDays(7)))->count(),
                'inactive_15d' => $users->filter(fn ($u) => Carbon::parse($u['last_played_at'] ?? $u['created_at'])->lt(now()->subDays(ReengagementService::STEP_DAYS)))->count(),
                'opted_out' => $users->where('receives_emails', false)->count(),
                'emails_sent_30d' => ReengagementEmail::where('sent_at', '>=', $since30)->count(),
                'gifts_claimed_30d' => ReengagementEmail::where('sent_at', '>=', $since30)->whereNotNull('claimed_at')->count(),
            ],
            'period' => $days,
            'daily_growth' => $this->dailyGrowth($days),
        ]);
    }

    /** Ficha completa de um usuário. */
    public function show(User $user): Response
    {
        $user = User::query()->withLastPlayed()->with('subscriptions')->findOrFail($user->id);
        $streaks = app(StreakService::class);
        $subscription = $user->subscription('default');
        $answers = $user->onboarding_answers ?? [];

        return Inertia::render('admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_config' => $user->publicAvatarConfig(),
                'is_admin' => $user->is_admin,
                'is_premium' => $user->hasActiveSubscription(),
                'lives' => $user->lives,
                'xp' => $user->xp,
                'current_streak' => $streaks->currentStreak($user->id),
                'longest_streak' => $streaks->longestStreak($user->id),
                'created_at' => $user->created_at->toIso8601String(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'last_played_at' => $user->lastPlayedAt()?->toIso8601String(),
                'goal' => $answers['goal'] ?? null,
                'area' => $answers['area'] ?? null,
                'minutes' => $answers['minutes'] ?? null,
                'onboarding_completed_at' => $user->onboarding_completed_at?->toIso8601String(),
                'receives_emails' => $user->receivesEmails(),
                'email_opt_out_at' => $user->email_opt_out_at?->toIso8601String(),
                'xp_boost_until' => $user->hasXpBoost() ? $user->xp_boost_until->toIso8601String() : null,
            ],
            'subscription' => $subscription ? [
                'status' => $subscription->stripe_status,
                'created_at' => $subscription->created_at?->toIso8601String(),
                'ends_at' => $subscription->ends_at?->toIso8601String(),
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            ] : null,
            'trail' => UserLegislationSelection::where('user_id', $user->id)
                ->with('legislation:id,title')
                ->get()
                ->pluck('legislation.title')
                ->filter()
                ->values(),
            'progress' => [
                'blocks_completed' => DB::table('user_segment_progress')->where('user_id', $user->id)->where('is_completed', true)->count(),
                'blocks_attempted' => DB::table('user_segment_progress')->where('user_id', $user->id)->count(),
                'articles_completed' => DB::table('user_progress')->where('user_id', $user->id)->where('is_completed', true)->count(),
                'xp_30d' => (int) $user->xpTransactions()->where('created_at', '>=', now()->subDays(30))->sum('amount'),
            ],
            'emails' => $user->reengagementEmails()
                ->orderByDesc('sent_at')
                ->get(['sequence', 'gift', 'inactive_days', 'sent_at', 'claimed_at'])
                ->map(fn (ReengagementEmail $email) => [
                    'sequence' => $email->sequence,
                    'gift' => $email->gift,
                    'inactive_days' => $email->inactive_days,
                    'sent_at' => $email->sent_at->toIso8601String(),
                    'claimed_at' => $email->claimed_at?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * Prévia do lembrete por e-mail, montada para o próprio admin, sem gravar
     * nem enviar nada. `?gift=xp_boost` e `?sequence=2` trocam a versão.
     */
    public function previewEmail(Request $request): ReengagementMail
    {
        $user = $request->user();
        $email = new ReengagementEmail([
            'uuid' => 'previa',
            'sequence' => max(1, min(3, (int) $request->query('sequence', 1))),
            'gift' => $request->query('gift') === ReengagementEmail::GIFT_XP_BOOST ? ReengagementEmail::GIFT_XP_BOOST : ReengagementEmail::GIFT_LIVES,
            'inactive_days' => max(15, (int) $request->query('days', 21)),
            'sent_at' => now(),
        ]);
        $email->setRelation('user', $user);

        return new ReengagementMail($email);
    }

    public function exportBrevoContacts(): StreamedResponse
    {
        $users = User::query()
            ->select('id', 'name', 'email')
            ->with(['legalReferences:id,name'])
            ->orderBy('id')
            ->get();

        $filename = 'brevo-contacts-'.now()->format('Y-m-d-H-i').'.csv';

        return response()->streamDownload(function () use ($users): void {
            $output = fopen('php://output', 'wb');

            fputcsv($output, [
                'CONTACT ID',
                'EMAIL',
                'FIRSTNAME',
                'LASTNAME',
                'SMS',
                'LANDLINE_NUMBER',
                'WHATSAPP',
                'INTERESTS',
            ]);

            foreach ($users as $user) {
                [$firstName, $lastName] = $this->splitName($user->name);

                fputcsv($output, [
                    (string) $user->id,
                    (string) $user->email,
                    $firstName,
                    $lastName,
                    '',
                    '',
                    '',
                    $this->formatInterests($user->legalReferences->pluck('name')),
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Add lives to a user.
     */
    public function addLives(Request $request, User $user): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'lives' => 'required|integer|min:1|max:10',
        ]);

        $user->incrementLife($request->lives);

        return back()->with('success', "Adicionadas {$request->lives} vidas ao usuário {$user->name}");
    }

    private function activeSubscriptionUserIds(): Collection
    {
        return Subscription::query()
            ->where('type', 'default')
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->distinct()
            ->pluck('user_id');
    }

    /**
     * Novos cadastros e novos pagantes por dia, de `$days` dias atrás até hoje.
     *
     * @return Collection<int, array{date: string, label: string, users: int, paying_users: int}>
     */
    private function dailyGrowth(int $days): Collection
    {
        $startDate = now()->subDays($days - 1)->startOfDay();
        $endDate = now()->endOfDay();

        $usersByDay = User::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('day')
            ->pluck('total', 'day')
            ->all();

        $payingUsersByDay = Subscription::query()
            ->selectRaw('DATE(created_at) as day, COUNT(DISTINCT user_id) as total')
            ->where('type', 'default')
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('day')
            ->pluck('total', 'day')
            ->all();

        return collect(CarbonPeriod::create($startDate, $endDate))
            ->map(function (Carbon $day) use ($usersByDay, $payingUsersByDay) {
                $key = $day->toDateString();

                return [
                    'date' => $key,
                    'label' => $day->format('d/m'),
                    'users' => (int) ($usersByDay[$key] ?? 0),
                    'paying_users' => (int) ($payingUsersByDay[$key] ?? 0),
                ];
            })
            ->values();
    }

    private function splitName(?string $fullName): array
    {
        $name = trim((string) $fullName);

        if ($name === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $firstName = (string) array_shift($parts);
        $lastName = implode(' ', $parts);

        return [$firstName, $lastName];
    }

    private function formatInterests(Collection $interests): string
    {
        if ($interests->isEmpty()) {
            return '[]';
        }

        $formatted = $interests
            ->map(fn (string $interest) => "'".str_replace("'", "''", $interest)."'")
            ->implode('|');

        return '['.$formatted.']';
    }
}
