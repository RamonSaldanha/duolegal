<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Planos de Assinatura
    |--------------------------------------------------------------------------
    |
    | Configure aqui os IDs dos preços do Stripe para cada plano.
    | Para alterar os preços, atualize os IDs abaixo com os IDs
    | correspondentes do painel do Stripe.
    |
    */

    'plans' => [
        'monthly' => env('STRIPE_PRICE_MONTHLY', 'price_1StCFJGjTEIZborGaTlExEMF'),
        'quarterly' => env('STRIPE_PRICE_QUARTERLY', 'price_1StIQJGjTEIZborGfWvfFumD'),
        'yearly' => env('STRIPE_PRICE_YEARLY', 'price_1StIR1GjTEIZborGzAc3WC2U'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Oferta de boas-vindas
    |--------------------------------------------------------------------------
    |
    | Desconto mostrado no fim do onboarding, só no plano anual e só por
    | algumas horas. O cupom é criado no Stripe na primeira assinatura que
    | usar a oferta, se ainda não existir lá com este id.
    |
    */

    'welcome_offer' => [
        'coupon' => env('STRIPE_WELCOME_COUPON', 'BOASVINDAS15'),
        'percent_off' => 15,
        'hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Oferta da temporada (Black Friday)
    |--------------------------------------------------------------------------
    |
    | Desconto aplicado sozinho, sem digitar cupom, na página de assinatura
    | (e anunciado no mapa) para quem não assina, entre starts_at e ends_at
    | (horário de Brasília). Vale só na primeira cobrança: a renovação volta
    | ao preço normal. O cupom é criado no Stripe na primeira assinatura que
    | o usar. Ganha da oferta de boas-vindas
    | quando o desconto é maior. Para desligar antes da hora, mude ends_at.
    |
    */

    'seasonal_offer' => [
        'name' => 'Black Friday',
        'starts_at' => '2026-11-27 00:00:00',
        'ends_at' => '2026-12-02 23:59:59',
        'plans' => [
            'monthly' => ['coupon' => 'BLACKFRIDAY26-30', 'percent_off' => 30],
            'quarterly' => ['coupon' => 'BLACKFRIDAY26-30', 'percent_off' => 30],
            'yearly' => ['coupon' => 'BLACKFRIDAY26-30', 'percent_off' => 30],
        ],
    ],
];
