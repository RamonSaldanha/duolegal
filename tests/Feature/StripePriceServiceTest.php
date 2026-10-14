<?php

use App\Services\StripePriceService;
use Illuminate\Support\Facades\Cache;

test('sem resposta do Stripe, usa o último preço conhecido', function () {
    config(['cashier.secret' => 'sk_test_invalida']);

    $last = [
        'price_id' => 'price_teste',
        'price' => 10791,
        'monthly_price' => 899,
        'interval' => 'year',
        'interval_count' => 1,
        'currency' => 'brl',
    ];
    Cache::forever('stripe-price:v2:price_teste:last', $last);

    expect(app(StripePriceService::class)->find('price_teste'))->toBe($last);
});

test('sem resposta do Stripe e sem preço guardado, devolve nulo', function () {
    config(['cashier.secret' => 'sk_test_invalida']);

    expect(app(StripePriceService::class)->find('price_nunca_visto'))->toBeNull();
});
