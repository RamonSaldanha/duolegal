<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Services\DisciplineStarService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request, DisciplineStarService $stars): Response
    {
        $hidden = $request->user()->hidden_achievements ?? [];

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'receivesEmails' => $request->user()->receivesEmails(),
            'bio' => $request->user()->bio,
            'profileUrl' => $request->user()->uuid ? route('profiles.show', $request->user()->uuid) : null,
            'achievements' => $stars->earnedFor($request->user())->map(fn (array $progress) => [
                'id' => $progress['id'],
                'name' => $progress['name'],
                'stars' => $progress['stars'],
                'badge_url' => $progress['badge_url'],
                'hidden' => in_array($progress['id'], $hidden),
            ]),
        ]);
    }

    /**
     * Escolhe quais conquistas aparecem no perfil público.
     */
    public function updateAchievements(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hidden' => ['present', 'array'],
            'hidden.*' => ['integer', 'exists:disciplines,id'],
        ]);

        $request->user()->hidden_achievements = array_map('intval', $validated['hidden']);
        $request->user()->save();

        return to_route('profile.edit');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
