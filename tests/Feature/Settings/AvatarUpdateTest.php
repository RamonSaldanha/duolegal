<?php

use App\Models\User;
use Laravel\Cashier\Subscription;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

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
 * Avatar válido, montado a partir do primeiro id de cada grupo.
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

test('a página do avatar exige login', function () {
    $this->get('/settings/avatar')->assertRedirect('/login');
});

test('a página do avatar é exibida para quem está logado', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/avatar')
        ->assertOk();
});

test('o avatar é salvo', function () {
    $user = User::factory()->create();
    // Segunda opção de cada grupo, para o teste não depender dos nomes dos ids.
    $avatar = validAvatar(['body' => config('avatar.shapes.body')[1], 'background' => '#F0A93F']);

    $this->actingAs($user)
        ->put('/settings/avatar', ['avatar' => $avatar])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    // `validated()` devolve as chaves na ordem das regras, não na ordem enviada.
    $saved = $user->fresh()->avatar_config;
    ksort($saved);
    ksort($avatar);

    expect($saved)->toBe($avatar);
});

test('id de peça fora da lista é rejeitado', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/settings/avatar', ['avatar' => validAvatar(['body' => 'musculoso'])])
        ->assertSessionHasErrors('avatar.body');

    expect($user->fresh()->avatar_config)->toBeNull();
});

test('cor malformada é rejeitada', function () {
    $user = User::factory()->create();

    // Tentativa de injetar markup pelo campo de cor: o regex de `#rrggbb` barra.
    $this->actingAs($user)
        ->put('/settings/avatar', ['avatar' => validAvatar(['skin' => '"/><script>alert(1)</script>'])])
        ->assertSessionHasErrors('avatar.skin');

    expect($user->fresh()->avatar_config)->toBeNull();
});

test('avatar incompleto é rejeitado', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/settings/avatar', ['avatar' => ['skin' => '#ABCDEF']])
        ->assertSessionHasErrors('avatar.body');
});

test('salvar avatar exige login', function () {
    $this->put('/settings/avatar', ['avatar' => validAvatar()])->assertRedirect('/login');
});

test('o avatar chega nas props compartilhadas do Inertia', function () {
    $avatar = validAvatar();
    $user = User::factory()->create();
    $user->avatar_config = $avatar;
    $user->save();

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertInertia(fn ($page) => $page->where('auth.user.avatar_config', $avatar));
});

test('fantasia exclusiva é recusada para quem não assina', function (string $outfit) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/settings/avatar', ['avatar' => validAvatar(['outfit' => $outfit])])
        ->assertSessionHasErrors('avatar.outfit');

    expect($user->fresh()->avatar_config)->toBeNull();
})->with(fn () => config('avatar.premium.outfit'));

test('assinante pode vestir a fantasia', function (string $outfit) {
    $user = User::factory()->create();
    giveActiveSubscription($user);

    $this->actingAs($user)
        ->put('/settings/avatar', ['avatar' => validAvatar(['outfit' => $outfit])])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->avatar_config['outfit'])->toBe($outfit);
})->with(fn () => config('avatar.premium.outfit'));

test('a fantasia aparece para quem assina', function () {
    $user = User::factory()->create();
    giveActiveSubscription($user);
    $user->avatar_config = validAvatar(['outfit' => 'toga']);
    $user->save();

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertInertia(fn ($page) => $page->where('auth.user.avatar_config.outfit', 'toga'));
});

test('quem deixou de assinar não exibe a fantasia, mas não a perde', function () {
    $user = User::factory()->create();
    $user->avatar_config = validAvatar(['outfit' => 'toga']);
    $user->save();

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertInertia(fn ($page) => $page->where('auth.user.avatar_config.outfit', 'nenhum'));

    // O que está salvo continua lá: se voltar a assinar, a toga volta.
    expect($user->fresh()->avatar_config['outfit'])->toBe('toga');
});
