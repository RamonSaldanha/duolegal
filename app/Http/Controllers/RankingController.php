<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class RankingController extends Controller
{
    public function index(): Response
    {
        $currentUser = auth()->user();

        $topUsers = User::select('id', 'name', 'xp')
            ->orderByDesc('xp')
            ->limit(20)
            ->get()
            ->map(function ($user, $index) {
                return [
                    'id' => $user->id,
                    'first_name' => explode(' ', trim($user->name))[0],
                    'last_name' => count(explode(' ', trim($user->name))) > 1
                        ? explode(' ', trim($user->name))[count(explode(' ', trim($user->name))) - 1]
                        : '',
                    'xp' => $user->xp,
                    'position' => $index + 1,
                ];
            });

        // Calcular posição do usuário atual
        $currentUserPosition = null;
        $currentUserData = null;

        if ($currentUser) {
            $allUsers = User::select('id', 'name', 'xp')
                ->orderByDesc('xp')
                ->get();

            foreach ($allUsers as $index => $user) {
                if ($user->id === $currentUser->id) {
                    $currentUserPosition = $index + 1;
                    $currentUserData = [
                        'id' => $user->id,
                        'first_name' => explode(' ', trim($user->name))[0],
                        'last_name' => count(explode(' ', trim($user->name))) > 1
                            ? explode(' ', trim($user->name))[count(explode(' ', trim($user->name))) - 1]
                            : '',
                        'xp' => $user->xp,
                        'position' => $currentUserPosition,
                    ];
                    break;
                }
            }
        }

        return Inertia::render('Ranking/Index', [
            'topUsers' => $topUsers,
            'currentUserPosition' => $currentUserPosition,
            'currentUserData' => $currentUserData,
        ]);
    }
}
