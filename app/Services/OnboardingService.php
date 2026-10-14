<?php

namespace App\Services;

use App\Models\Legislation;
use App\Models\User;
use App\Models\UserLegislationSelection;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Regras do onboarding de quem acabou de se cadastrar: a trilha montada pelo
 * quiz, o XP do desafio, o relógio da oferta de boas-vindas e o fim do fluxo.
 *
 * O site (OnboardingController) e o app (Api\V1\OnboardingController) passam
 * pelas mesmas regras daqui; cada um só responde do seu jeito (redirect do
 * Inertia ou JSON).
 */
class OnboardingService
{
    public const GOALS = ['oab', 'concurso', 'faculdade', 'profissao', 'pessoal'];

    public const AREAS = ['constitucional', 'penal', 'civil', 'trabalho', 'administrativo', 'indica'];

    public const MINUTES = [5, 15, 30, 60];

    /** XP do desafio do onboarding. Vale uma vez por conta. */
    public const CHALLENGE_XP = 25;

    /** Validação das respostas do quiz + a trilha final. */
    public function trailRules(): array
    {
        return [
            'goal' => ['required', Rule::in(self::GOALS)],
            'area' => ['required', Rule::in(self::AREAS)],
            'minutes' => ['required', 'integer', Rule::in(self::MINUTES)],
            'legislation_ids' => ['required', 'array', 'min:1'],
            'legislation_ids.*' => ['integer'],
        ];
    }

    /** Conta antiga, cadastro pelo app antigo ou onboarding já feito: nada daqui vale de novo. */
    public function completed(User $user): bool
    {
        return $user->onboarding_completed_at !== null;
    }

    /** Leis que podem entrar na trilha. A sugestão do quiz acha cada uma pelo título. */
    public function legislations(): Collection
    {
        return Legislation::where('status', 'published')
            ->orderBy('id')
            ->get(['id', 'title']);
    }

    public function challengeDone(User $user): bool
    {
        return $user->xpTransactions()->where('source_type', 'onboarding')->exists();
    }

    /**
     * Preço do plano anual em centavos, para a tela da oferta. Mesma fonte do
     * checkout (StripePriceService), para os dois mostrarem o mesmo valor.
     *
     * @return array{price: int, monthly_price: int}|null
     */
    public function yearlyPlan(): ?array
    {
        $price = app(StripePriceService::class)->find(config('subscription.plans.yearly'));

        return $price ? ['price' => $price['price'], 'monthly_price' => $price['monthly_price']] : null;
    }

    /**
     * A oferta de boas-vindas como as telas mostram, ou nulo fora das 24h.
     *
     * @return array{percent_off: int, expires_at: string}|null
     */
    public function welcomeOffer(User $user): ?array
    {
        return $user->hasWelcomeOffer() ? [
            'percent_off' => config('subscription.welcome_offer.percent_off'),
            'expires_at' => $user->welcome_offer_expires_at->toIso8601String(),
        ] : null;
    }

    /**
     * Guarda as respostas e troca a seleção de legislações pela trilha. É a
     * mesma seleção que o mapa da web e o do app usam, então vale nos dois.
     *
     * @param  array{goal: string, area: string, minutes: int|string, legislation_ids: array<int>}  $validated
     */
    public function saveTrail(User $user, array $validated): void
    {
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
    }

    /** O desafio conta XP de verdade, uma vez só. Devolve o XP ganho agora. */
    public function completeChallenge(User $user): int
    {
        if ($this->challengeDone($user)) {
            return 0;
        }

        $user->addXp(self::CHALLENGE_XP, 'onboarding');

        return self::CHALLENGE_XP;
    }

    /** Liga o relógio das 24h quando a tela da oferta aparece pela primeira vez. */
    public function startOffer(User $user): void
    {
        if ($user->welcome_offer_expires_at === null && ! $user->hasActiveSubscription()) {
            $user->welcome_offer_expires_at = now()->addHours(config('subscription.welcome_offer.hours'));
            $user->save();
        }
    }

    /**
     * Fim do fluxo. Quem pula antes da oferta não ganha a oferta; quem chega
     * nela segue com as 24h valendo, aceite agora ou não.
     */
    public function finish(User $user): void
    {
        $user->onboarding_completed_at ??= now();
        $user->save();
    }
}
