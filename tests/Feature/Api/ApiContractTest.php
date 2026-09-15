<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Trava do contrato da API v1.
 *
 * O app Android consome estas rotas. O criador de avatar é, por enquanto, um
 * recurso só da web: `avatar_config` não pode vazar para cá sem decisão explícita.
 * Se alguém acrescentar o campo no UserResource ou tirar `avatar_config` do
 * `$hidden` do User, estes testes quebram.
 */
function userWithAvatar(): User
{
    $user = User::factory()->create();
    $user->avatar_config = ['face' => 'quadrado', 'skin' => '#F0B489'];
    $user->save();

    return $user;
}

test('GET /api/v1/me devolve exatamente os campos de hoje', function () {
    Sanctum::actingAs(userWithAvatar());

    $payload = $this->getJson('/api/v1/me')->assertOk()->json();

    expect(array_keys($payload))->toEqualCanonicalizing([
        'id',
        'name',
        'email',
        'lives',
        'has_infinite_lives',
        'xp',
        'current_streak',
        'longest_streak',
        'is_admin',
        'email_verified_at',
    ]);
});

test('GET /api/user não expõe o avatar', function () {
    Sanctum::actingAs(userWithAvatar());

    $this->getJson('/api/user')
        ->assertOk()
        ->assertJsonMissingPath('avatar_config');
});

test('o ranking da API não muda de forma', function () {
    $user = userWithAvatar();
    Sanctum::actingAs($user);

    $payload = $this->getJson('/api/v1/ranking')->assertOk()->json();

    expect(array_keys($payload))->toEqualCanonicalizing([
        'top_users',
        'current_user_position',
        'current_user_data',
        'period',
    ]);
});

test('o model serializado esconde o avatar', function () {
    expect(userWithAvatar()->toArray())->not->toHaveKey('avatar_config');
});
