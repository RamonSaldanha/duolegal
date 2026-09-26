<?php

use App\Models\User;
use Laravel\Cashier\Subscription;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

// As fantasias exclusivas de assinante, lidas direto de config/avatar.php: o
// dataset é montado antes de o Laravel subir, quando config() ainda não existe.
dataset('fantasias premium', fn () => (require __DIR__.'/../config/avatar.php')['premium']['outfit']);

/**
 * Assinatura ativa no plano `default`, como o Cashier grava depois do Stripe.
 */
function giveActiveSubscription(User $user): void
{
    Subscription::query()->create([
        'user_id' => $user->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'quantity' => 1,
    ]);
}

/**
 * Avatar válido, montado a partir do primeiro id de cada grupo. Usado pelos
 * testes do editor da web e da API do app.
 *
 * @return array<string, string>
 */
function validAvatar(array $overrides = []): array
{
    $avatar = [];

    foreach (config('avatar.shapes') as $key => $allowed) {
        $avatar[$key] = $allowed[0];
    }

    foreach (config('avatar.colors') as $key) {
        $avatar[$key] = '#ABCDEF';
    }

    return array_merge($avatar, $overrides);
}
