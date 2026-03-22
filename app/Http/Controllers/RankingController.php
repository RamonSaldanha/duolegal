<?php

namespace App\Http\Controllers;

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

        $topUsers = $this->xpService->getRanking($period, 20)
            ->values()
            ->map(function ($user, $index) {
                return [
                    'id' => $user->id,
                    'first_name' => explode(' ', trim($user->name))[0],
                    'last_name' => count(explode(' ', trim($user->name))) > 1
                        ? explode(' ', trim($user->name))[count(explode(' ', trim($user->name))) - 1]
                        : '',
                    'xp' => (int) $user->total_xp,
                    'position' => $index + 1,
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
