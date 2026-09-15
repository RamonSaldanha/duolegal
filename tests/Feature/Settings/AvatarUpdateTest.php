<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

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
