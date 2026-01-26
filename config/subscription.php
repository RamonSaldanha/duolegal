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
];
