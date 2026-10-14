<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\Price;
use Stripe\Stripe;

/**
 * Preços dos planos vindos do Stripe, com cache.
 *
 * O checkout e a oferta do onboarding leem daqui, para mostrarem sempre o
 * mesmo valor. A consulta fica em cache por uma hora, e o último preço que o
 * Stripe devolveu fica guardado sem prazo: se o Stripe não responder, as telas
 * seguem mostrando o preço real em vez de um valor fixo desatualizado.
 */
class StripePriceService
{
    /**
     * @return array{price_id: string, price: int, monthly_price: int, interval: string, interval_count: int, currency: string}|null
     */
    public function find(?string $priceId): ?array
    {
        if (empty($priceId)) {
            return null;
        }

        $key = "stripe-price:v2:{$priceId}";

        try {
            return Cache::remember($key, now()->addHour(), function () use ($priceId, $key) {
                Stripe::setApiKey(config('cashier.secret'));
                $price = Price::retrieve($priceId);

                $interval = $price->recurring->interval ?? 'month';
                $intervalCount = $price->recurring->interval_count ?? 1;
                $amount = (int) $price->unit_amount;

                // Preço mensal equivalente
                $monthly = match (true) {
                    $interval === 'year' => (int) round($amount / 12),
                    $interval === 'month' && $intervalCount > 1 => (int) round($amount / $intervalCount),
                    default => $amount,
                };

                $data = [
                    'price_id' => $priceId,
                    'price' => $amount,
                    'monthly_price' => $monthly,
                    'interval' => $interval,
                    'interval_count' => $intervalCount,
                    'currency' => $price->currency,
                ];

                Cache::forever("{$key}:last", $data);

                return $data;
            });
        } catch (\Exception $e) {
            Log::warning('Preço do Stripe indisponível, usando o último conhecido', [
                'price_id' => $priceId,
                'error' => $e->getMessage(),
            ]);

            return Cache::get("{$key}:last");
        }
    }
}
