<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Stripe\Coupon;
use Stripe\Exception\InvalidRequestException;
use Stripe\Stripe;

/**
 * A oferta da temporada (config/subscription.php, seasonal_offer): um desconto
 * por plano, para todos, dentro de uma janela de datas no horário de Brasília.
 */
class SeasonalOfferService
{
    private const TIMEZONE = 'America/Sao_Paulo';

    public function isActive(): bool
    {
        $config = config('subscription.seasonal_offer');

        if (empty($config['plans']) || empty($config['starts_at']) || empty($config['ends_at'])) {
            return false;
        }

        $now = Carbon::now();

        return $now->gte($this->date($config['starts_at'])) && $now->lte($this->endsAt());
    }

    public function endsAt(): Carbon
    {
        return $this->date(config('subscription.seasonal_offer.ends_at'));
    }

    /** Desconto do plano ('yearly', 'quarterly'...) enquanto a oferta vale; 0 fora dela. */
    public function percentFor(string $plan): int
    {
        if (! $this->isActive()) {
            return 0;
        }

        return (int) (config("subscription.seasonal_offer.plans.{$plan}.percent_off") ?? 0);
    }

    /** O que a página de assinatura recebe, ou null fora da janela. */
    public function forPage(): ?array
    {
        if (! $this->isActive()) {
            return null;
        }

        return [
            'name' => config('subscription.seasonal_offer.name'),
            'ends_at' => $this->endsAt()->toIso8601String(),
            'plans' => collect(config('subscription.seasonal_offer.plans'))
                ->map(fn (array $plan) => (int) $plan['percent_off'])
                ->all(),
        ];
    }

    /** O plano da configuração ('yearly'...) a que um price_id do Stripe pertence. */
    public function planForPrice(string $priceId): ?string
    {
        $plan = array_search($priceId, config('subscription.plans', []), true);

        return $plan === false ? null : $plan;
    }

    /**
     * Id do cupom do plano no Stripe. Se ainda não existe lá, é criado aqui
     * (percentual, só na primeira cobrança).
     */
    public function couponId(string $plan): string
    {
        $config = config("subscription.seasonal_offer.plans.{$plan}");

        Stripe::setApiKey(config('cashier.secret'));

        try {
            return Coupon::retrieve($config['coupon'])->id;
        } catch (InvalidRequestException $e) {
            return Coupon::create([
                'id' => $config['coupon'],
                'name' => config('subscription.seasonal_offer.name').": {$config['percent_off']}% na 1ª cobrança",
                'percent_off' => $config['percent_off'],
                'duration' => 'once',
            ])->id;
        }
    }

    private function date(string $value): Carbon
    {
        return Carbon::parse($value, self::TIMEZONE);
    }
}
