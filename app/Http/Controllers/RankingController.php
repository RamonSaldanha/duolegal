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

        // Consulta à parte em vez de mexer no select do XpService, que só sabe de
        // XP. O RankingController da API busca os avatares do mesmo jeito.
        $avatars = User::publicAvatarConfigsFor($ranking->pluck('id'));

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
                    'avatar_config' => $currentUser->publicAvatarConfig(),
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
}
