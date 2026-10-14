<?php

namespace App\Http\Controllers;

use App\Models\Legislation;
use App\Models\UserLegislationSelection;
use App\Services\StripePriceService;
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
 * Só o cadastro web manda para cá. Contas antigas e cadastros pelo app (API)
 * já têm `onboarding_completed_at` preenchido e são mandados para o mapa.
 * Nada aqui trava rota do jogo.
 */
class OnboardingController extends Controller
{
    public const GOALS = ['oab', 'concurso', 'faculdade', 'profissao', 'pessoal'];

    public const AREAS = ['constitucional', 'penal', 'civil', 'trabalho', 'administrativo', 'indica'];

    public const MINUTES = [5, 15, 30, 60];

    /** XP do desafio do onboarding. Vale uma vez por conta. */
    public const CHALLENGE_XP = 25;

    public function show(): Response|RedirectResponse
    {
        $user = Auth::user();

        if ($user->onboarding_completed_at) {
            return redirect()->route('play.map');
        }

        $legislations = Legislation::where('status', 'published')
            ->orderBy('id')
            ->get(['id', 'title']);

        return Inertia::render('Onboarding/Index', [
            'legislations' => $legislations,
            'challengeDone' => $this->challengeDone(),
            'yearlyPlan' => $this->yearlyPlan(),
            'welcomeOffer' => $user->hasWelcomeOffer() ? [
                'percent_off' => config('subscription.welcome_offer.percent_off'),
                'expires_at' => $user->welcome_offer_expires_at->toIso8601String(),
            ] : null,
        ]);
    }

    /**
     * Respostas do quiz + a trilha final. A trilha vai para a mesma seleção que
     * o mapa da web e o app usam, então a personalização vale nos dois.
     */
    public function saveTrail(Request $request): RedirectResponse
    {
        if ($this->alreadyOnboarded()) {
            return redirect()->route('play.map');
        }

        $validated = $request->validate([
            'goal' => ['required', Rule::in(self::GOALS)],
            'area' => ['required', Rule::in(self::AREAS)],
            'minutes' => ['required', 'integer', Rule::in(self::MINUTES)],
            'legislation_ids' => ['required', 'array', 'min:1'],
            'legislation_ids.*' => ['integer'],
        ]);

        $user = Auth::user();

        $validIds = Legislation::whereIn('id', $validated['legislation_ids'])
            ->where('status', 'published')
            ->pluck('id');

        $user->onboarding_answers = [
            'goal' => $validated['goal'],
            'area' => $validated['area'],
            'minutes' => (int) $validated['minutes'],
        ];
        $user->save();

        if ($validIds->isNotEmpty()) {
            UserLegislationSelection::where('user_id', $user->id)->delete();
            foreach ($validIds as $legislationId) {
                UserLegislationSelection::create([
                    'user_id' => $user->id,
                    'legislation_id' => $legislationId,
                ]);
            }
        }

        return back();
    }

    /** O desafio do onboarding conta XP de verdade, uma vez só. */
    public function completeChallenge(): RedirectResponse
    {
        if ($this->alreadyOnboarded()) {
            return redirect()->route('play.map');
        }

        if (! $this->challengeDone()) {
            Auth::user()->addXp(self::CHALLENGE_XP, 'onboarding');
        }

        return back();
    }

    /** Liga o relógio das 24h quando a tela da oferta aparece pela primeira vez. */
    public function startOffer(): RedirectResponse
    {
        if ($this->alreadyOnboarded()) {
            return redirect()->route('play.map');
        }

        $user = Auth::user();

        if ($user->welcome_offer_expires_at === null && ! $user->hasActiveSubscription()) {
            $user->welcome_offer_expires_at = now()->addHours(config('subscription.welcome_offer.hours'));
            $user->save();
        }

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
        $user->onboarding_completed_at ??= now();
        $user->save();

        if ($validated['next'] === 'subscription' && $user->hasWelcomeOffer()) {
            return redirect()->route('subscription.index');
        }

        return redirect()->route('play.map');
    }

    /** Conta antiga, cadastro pelo app ou onboarding já feito: nada daqui vale de novo. */
    private function alreadyOnboarded(): bool
    {
        return Auth::user()->onboarding_completed_at !== null;
    }

    private function challengeDone(): bool
    {
        return Auth::user()->xpTransactions()->where('source_type', 'onboarding')->exists();
    }

    /**
     * Preço do plano anual em centavos, para a tela da oferta. Mesma fonte do
     * checkout (StripePriceService), para os dois mostrarem o mesmo valor.
     *
     * @return array{price: int, monthly_price: int}|null
     */
    private function yearlyPlan(): ?array
    {
        $price = app(StripePriceService::class)->find(config('subscription.plans.yearly'));

        return $price ? ['price' => $price['price'], 'monthly_price' => $price['monthly_price']] : null;
    }
}
