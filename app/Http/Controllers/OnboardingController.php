<?php

namespace App\Http\Controllers;

use App\Services\OnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Onboarding de quem acabou de se cadastrar pelo site: quiz curto que monta a
 * trilha (seleção de legislações), um desafio de verdade, a apresentação do
 * avatar e a oferta de boas-vindas.
 *
 * Só o cadastro web manda para cá. O app tem o mesmo fluxo pela API
 * (Api\V1\OnboardingController), com as mesmas regras (OnboardingService).
 * Contas antigas já têm `onboarding_completed_at` preenchido e são mandadas
 * para o mapa. Nada aqui trava rota do jogo.
 */
class OnboardingController extends Controller
{
    public function __construct(private OnboardingService $onboarding) {}

    public function show(): Response|RedirectResponse
    {
        $user = Auth::user();

        if ($this->onboarding->completed($user)) {
            return redirect()->route('play.map');
        }

        return Inertia::render('Onboarding/Index', [
            'legislations' => $this->onboarding->legislations(),
            'challengeDone' => $this->onboarding->challengeDone($user),
            'yearlyPlan' => $this->onboarding->yearlyPlan(),
            'welcomeOffer' => $this->onboarding->welcomeOffer($user),
        ]);
    }

    /**
     * Respostas do quiz + a trilha final. A trilha vai para a mesma seleção que
     * o mapa da web e o app usam, então a personalização vale nos dois.
     */
    public function saveTrail(Request $request): RedirectResponse
    {
        if ($this->onboarding->completed(Auth::user())) {
            return redirect()->route('play.map');
        }

        $this->onboarding->saveTrail(Auth::user(), $request->validate($this->onboarding->trailRules()));

        return back();
    }

    /** O desafio do onboarding conta XP de verdade, uma vez só. */
    public function completeChallenge(): RedirectResponse
    {
        if ($this->onboarding->completed(Auth::user())) {
            return redirect()->route('play.map');
        }

        $this->onboarding->completeChallenge(Auth::user());

        return back();
    }

    /** Liga o relógio das 24h quando a tela da oferta aparece pela primeira vez. */
    public function startOffer(): RedirectResponse
    {
        if ($this->onboarding->completed(Auth::user())) {
            return redirect()->route('play.map');
        }

        $this->onboarding->startOffer(Auth::user());

        return back();
    }

    /**
     * Fim do fluxo. Quem pula antes da oferta não ganha a oferta; quem chega
     * nela segue com as 24h valendo, aceite agora ou não.
     */
    public function finish(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'next' => ['required', Rule::in(['map', 'subscription'])],
        ]);

        $user = Auth::user();
        $this->onboarding->finish($user);

        if ($validated['next'] === 'subscription' && $user->hasWelcomeOffer()) {
            return redirect()->route('subscription.index');
        }

        return redirect()->route('play.map');
    }
}
