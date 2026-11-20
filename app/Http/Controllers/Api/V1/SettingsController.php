<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DisciplineStarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O que o app mostra no Perfil além do UserResource, igual a Configurações da web: a
 * descrição, as conquistas que aparecem no perfil público e os e-mails de lembrete.
 */
class SettingsController extends Controller
{
    public function __construct(
        private DisciplineStarService $stars
    ) {}

    /** GET /v1/settings */
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request));
    }

    /** PUT /v1/settings/achievements — as disciplinas escondidas do perfil público. */
    public function updateAchievements(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hidden' => ['present', 'array'],
            'hidden.*' => ['integer', 'exists:disciplines,id'],
        ]);

        $request->user()->hidden_achievements = array_map('intval', $validated['hidden']);
        $request->user()->save();

        return response()->json($this->payload($request));
    }

    /** PUT /v1/settings/emails — receber ou não os e-mails de lembrete. */
    public function updateEmails(Request $request): JsonResponse
    {
        $validated = $request->validate(['receives_emails' => ['required', 'boolean']]);

        $user = $request->user();
        $user->email_opt_out_at = $validated['receives_emails'] ? null : ($user->email_opt_out_at ?? now());
        $user->save();

        return response()->json($this->payload($request));
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $user = $request->user();
        $hidden = $user->hidden_achievements ?? [];

        return [
            'bio' => $user->bio,
            // O próprio perfil público, para o app abrir
            'profile_uuid' => $user->uuid,
            'receives_emails' => $user->receivesEmails(),
            'achievements' => $this->stars->earnedFor($user)->map(fn (array $progress) => [
                'id' => $progress['id'],
                'name' => $progress['name'],
                'stars' => $progress['stars'],
                'badge_url' => $progress['badge_url'],
                'hidden' => in_array($progress['id'], $hidden),
            ])->values(),
        ];
    }
}
