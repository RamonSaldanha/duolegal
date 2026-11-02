<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DisciplineStarService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class PublicProfileController extends Controller
{
    public function __construct(
        private DisciplineStarService $stars
    ) {}

    /**
     * O que um usuário vê de outro. Os campos são escolhidos um a um para nada além
     * disto chegar à tela: e-mail, nome completo e XP por disciplina ficam de fora.
     */
    public function show(Request $request, User $user): Response
    {
        $names = preg_split('/\s+/', trim($user->name));

        // Mesma fonte da página Conquistas, para os números sempre baterem. As que a
        // pessoa escondeu não saem do servidor, nem na contagem.
        $achievements = $this->stars->earnedFor($user)
            ->reject(fn (array $progress) => in_array($progress['id'], $user->hidden_achievements ?? []))
            ->map(fn (array $progress) => Arr::only($progress, ['uuid', 'name', 'stars', 'badge_url', 'icon', 'color']))
            ->values();

        return Inertia::render('Profile/Show', [
            'profile' => [
                'first_name' => $names[0],
                'last_name' => count($names) > 1 ? end($names) : '',
                'avatar_config' => $user->publicAvatarConfig(),
                'bio' => $user->bio,
                'streak' => $user->effectiveStreak(),
                'is_self' => $request->user()->is($user),
            ],
            'achievements' => $achievements,
        ]);
    }
}
