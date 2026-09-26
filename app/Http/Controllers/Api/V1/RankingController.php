<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\XpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RankingController extends Controller
{
    public function __construct(private XpService $xpService) {}

    public function index(Request $request): JsonResponse
    {
        $period = $request->get('period', 'all');
        if (! in_array($period, ['all', 'daily', 'weekly'])) {
            $period = 'all';
        }

        $user = $request->user();
        $topUsers = $this->xpService->getRanking($period, 20);
        $currentUserPosition = $this->xpService->getUserPositionInRanking($user->id, $period);
        $currentUserXp = $this->xpService->getUserXpForPeriod($user->id, $period);

        // Mesma consulta à parte do ranking da web: tira a fantasia de quem
        // deixou de assinar.
        $avatars = User::publicAvatarConfigsFor($topUsers->pluck('id'));

        $topUsersFormatted = $topUsers->map(function ($u, $index) use ($user, $avatars) {
            $nameParts = explode(' ', $u->name, 2);

            return [
                'id' => $u->id,
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1] ?? '',
                'xp' => (int) $u->total_xp,
                'position' => $index + 1,
                'is_current_user' => $u->id === $user->id,
                'avatar_config' => $avatars[$u->id] ?? null,
            ];
        });

        $currentUserInTop = $topUsersFormatted->firstWhere('is_current_user', true);

        $currentUserData = null;
        if (! $currentUserInTop && $currentUserPosition !== null) {
            $nameParts = explode(' ', $user->name, 2);
            $currentUserData = [
                'id' => $user->id,
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1] ?? '',
                'xp' => $currentUserXp,
                'position' => $currentUserPosition,
                'is_current_user' => true,
                'avatar_config' => $user->publicAvatarConfig(),
            ];
        }

        return response()->json([
            'top_users' => $topUsersFormatted->values(),
            'current_user_position' => $currentUserPosition,
            'current_user_data' => $currentUserData,
            'period' => $period,
        ]);
    }
}
