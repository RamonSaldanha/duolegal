<?php

use App\Models\User;
use App\Services\SeasonalOfferService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config([
        'subscription.seasonal_offer' => [
            'name' => 'Black Friday',
            'starts_at' => '2026-11-27 00:00:00',
            'ends_at' => '2026-12-01 23:59:59',
            'plans' => [
                'yearly' => ['coupon' => 'TESTE-ANUAL35', 'percent_off' => 35],
                'quarterly' => ['coupon' => 'TESTE-TRI20', 'percent_off' => 20],
            ],
        ],
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('a oferta vale só dentro da janela, no horário de Brasília', function () {
    $offer = app(SeasonalOfferService::class);

    Carbon::setTestNow(Carbon::parse('2026-11-27 02:59:59', 'UTC'));
    expect($offer->isActive())->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-11-27 03:00:00', 'UTC'));
    expect($offer->isActive())->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2026-12-02 02:59:59', 'UTC'));
    expect($offer->isActive())->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2026-12-02 03:00:00', 'UTC'));
    expect($offer->isActive())->toBeFalse()
        ->and($offer->percentFor('yearly'))->toBe(0)
        ->and($offer->forPage())->toBeNull();
});

test('cada plano tem o seu desconto, e o mensal fica de fora', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-28 12:00:00', 'America/Sao_Paulo'));
    $offer = app(SeasonalOfferService::class);

    expect($offer->percentFor('yearly'))->toBe(35)
        ->and($offer->percentFor('quarterly'))->toBe(20)
        ->and($offer->percentFor('monthly'))->toBe(0)
        ->and($offer->planForPrice(config('subscription.plans.yearly')))->toBe('yearly')
        ->and($offer->planForPrice('price_desconhecido'))->toBeNull()
        ->and($offer->forPage())->toMatchArray([
            'name' => 'Black Friday',
            'plans' => ['yearly' => 35, 'quarterly' => 20],
        ]);
});

test('se a oferta acabou depois de a tela abrir, não cobra o preço cheio sem avisar', function () {
    Carbon::setTestNow(Carbon::parse('2026-12-02 10:00:00', 'America/Sao_Paulo'));
    $user = User::factory()->create(['stripe_id' => 'cus_teste']);

    $this->actingAs($user)
        ->post(route('subscription.subscribe'), [
            'payment_method' => 'pm_teste',
            'price_id' => config('subscription.plans.yearly'),
            'seasonal_offer' => true,
        ])
        ->assertRedirect(route('subscription.index'))
        ->assertSessionHas('error', 'A oferta de Black Friday terminou. Confira o preço e tente de novo.');

    expect($user->fresh()->subscriptions()->count())->toBe(0);
});
