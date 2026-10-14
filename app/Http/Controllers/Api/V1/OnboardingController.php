<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O onboarding do site, para o app: mesmas telas, mesmas regras
 * (OnboardingService). Só cadastro feito pelo app novo (que manda
 * `onboarding: true` no registro) chega aqui com o onboarding pendente.
 *
 * Depois de concluído, nada daqui vale de novo: os POSTs respondem 409, que é
 * o que o redirect para o mapa faz no site.
 */
class OnboardingController extends Controller
{
    public function __construct(private OnboardingService $onboarding) {}

    /** GET /v1/onboarding — tudo o que as telas precisam de uma vez. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'completed' => $this->onboarding->completed($user),
            'legislations' => $this->onboarding->legislations(),
            'challenge_done' => $this->onboarding->challengeDone($user),
            'yearly_plan' => $this->onboarding->yearlyPlan(),
            'welcome_offer' => $this->onboarding->welcomeOffer($user),
        ]);
    }

    /** POST /v1/onboarding/trail — respostas do quiz + a trilha final. */
    public function saveTrail(Request $request): JsonResponse
    {
        if ($this->onboarding->completed($request->user())) {
            return $this->alreadyCompleted();
        }

        $this->onboarding->saveTrail($request->user(), $request->validate($this->onboarding->trailRules()));

        return response()->json(['success' => true]);
    }

    /** POST /v1/onboarding/challenge — XP do desafio, uma vez só. */
    public function completeChallenge(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($this->onboarding->completed($user)) {
            return $this->alreadyCompleted();
        }

        $xpGained = $this->onboarding->completeChallenge($user);

        return response()->json([
            'xp_gained' => $xpGained,
            'user' => new UserResource($user->fresh()),
        ]);
    }

    /** POST /v1/onboarding/offer — liga o relógio das 24h da oferta. */
    public function startOffer(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($this->onboarding->completed($user)) {
            return $this->alreadyCompleted();
        }

        $this->onboarding->startOffer($user);

        return response()->json(['welcome_offer' => $this->onboarding->welcomeOffer($user)]);
    }

    /**
     * POST /v1/onboarding/finish — fim do fluxo, concluído ou pulado. Devolve o
     * usuário já com a oferta (se houver), para o banner do mapa aparecer.
     */
    public function finish(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->onboarding->finish($user);

        return response()->json(['user' => new UserResource($user)]);
    }

    private function alreadyCompleted(): JsonResponse
    {
        return response()->json(['message' => 'Onboarding já concluído.'], 409);
    }
}
