<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Subscription;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    /**
     * Display a listing of all users.
     */
    public function index(): Response
    {
        $activeSubscriptionUserIds = Subscription::query()
            ->where('type', 'default')
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->distinct()
            ->pluck('user_id');

        $users = User::query()
            ->select('id', 'name', 'email', 'lives', 'is_admin', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (User $user) use ($activeSubscriptionUserIds) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'lives' => $user->lives,
                    'xp' => $user->xp,
                    'is_admin' => $user->is_admin,
                    'is_premium' => $activeSubscriptionUserIds->contains($user->id),
                    'created_at' => $user->created_at->format('d/m/Y H:i'),
                ];
            });

        $startDate = now()->subDays(19)->startOfDay();
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

        $dailyGrowth = collect(CarbonPeriod::create($startDate, $endDate))
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

        return Inertia::render('admin/Users/Index', [
            'users' => $users,
            'stats' => [
                'total_users' => User::query()->count(),
                'paying_users' => $activeSubscriptionUserIds->count(),
            ],
            'daily_growth' => $dailyGrowth,
        ]);
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
