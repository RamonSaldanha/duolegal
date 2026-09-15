<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AvatarUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AvatarController extends Controller
{
    /**
     * Mostra o editor de avatar.
     *
     * O avatar atual não vai como prop: já chega em `auth.user.avatar_config`
     * pelo HandleInertiaRequests, igual ao nome e ao e-mail em settings/Profile.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Avatar');
    }

    /**
     * Salva o avatar.
     *
     * Atribuição direta em vez de `fill()`: `avatar_config` fica fora do
     * `$fillable` para não entrar em massa por outros caminhos.
     */
    public function update(AvatarUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->avatar_config = $request->validated()['avatar'];
        $user->save();

        return back()->with('success', 'Avatar atualizado!');
    }
}
