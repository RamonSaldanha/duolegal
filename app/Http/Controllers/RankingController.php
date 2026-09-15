<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\XpService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RankingController extends Controller
{
    public function __construct(
        private XpService $xpService
    ) {}

    public function index(Request $request): Response
    {
        $period = $request->get('period', 'all');
        $currentUser = auth()->user();

        $ranking = $this->xpService->getRanking($period, 20)->values();

        // Consulta à parte em vez de mexer no select do XpService: o serviço é
        // compartilhado com o RankingController da API, que precisa continuar
        // devolvendo exatamente o que devolve hoje.
        $avatars = $this->avatarsFor($ranking->pluck('id'));

        $topUsers = $ranking->map(function ($user, $index) use ($avatars) {
            return [
                'id' => $user->id,
                'first_name' => explode(' ', trim($user->name))[0],
                'last_name' => count(explode(' ', trim($user->name))) > 1
                    ? explode(' ', trim($user->name))[count(explode(' ', trim($user->name))) - 1]
                    : '',
                'xp' => (int) $user->total_xp,
                'position' => $index + 1,
                'avatar_config' => $avatars[$user->id] ?? null,
            ];
        });

        $currentUserPosition = null;
        $currentUserData = null;

        if ($currentUser) {
            $currentUserPosition = $this->xpService->getUserPositionInRanking($currentUser->id, $period);
            $userXp = $this->xpService->getUserXpForPeriod($currentUser->id, $period);

            if ($userXp > 0) {
                $currentUserData = [
                    'id' => $currentUser->id,
                    'first_name' => explode(' ', trim($currentUser->name))[0],
                    'last_name' => count(explode(' ', trim($currentUser->name))) > 1
                        ? explode(' ', trim($currentUser->name))[count(explode(' ', trim($currentUser->name))) - 1]
                        : '',
                    'xp' => $userXp,
                    'position' => $currentUserPosition,
                    'avatar_config' => $currentUser->avatar_config,
                ];
            }
        }

        return Inertia::render('Ranking/Index', [
            'topUsers' => $topUsers,
            'currentUserPosition' => $currentUserPosition,
            'currentUserData' => $currentUserData,
            'period' => $period,
        ]);
    }

    /**
     * Avatares dos usuários do ranking, indexados por id.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $ids
     * @return \Illuminate\Support\Collection<int, array<string, string>|null>
     */
    private function avatarsFor($ids)
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $ids)
            ->pluck('avatar_config', 'id')
            ->map(fn ($config) => is_string($config) ? json_decode($config, true) : $config);
    }
}
