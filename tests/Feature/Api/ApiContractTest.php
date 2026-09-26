<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Trava do contrato da API v1.
 *
 * O app Android consome estas rotas. O avatar entrou na API por decisão
 * explícita, para o app desenhar o mesmo boneco da web, e só por dois
 * caminhos: `avatar_config` no UserResource (`/me`, login e cadastro) e em
 * cada entrada do ranking — sempre por publicAvatarConfig(). O model continua
 * escondendo o campo, então `/api/user` e quem serializa o User inteiro não o
 * recebem. Mudou um desses campos? Estes testes quebram.
 */
function userWithAvatar(): User
{
    $user = User::factory()->create();
    $user->avatar_config = ['face' => 'quadrado', 'skin' => '#F0B489'];
    $user->save();

    return $user;
}

test('GET /api/v1/me devolve exatamente estes campos', function () {
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
        'avatar_config',
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
    // Com XP, o usuário entra no ranking e dá para conferir a forma de uma entrada.
    $user->addXp(10, 'play');
    Sanctum::actingAs($user);

    $payload = $this->getJson('/api/v1/ranking')->assertOk()->json();

    expect(array_keys($payload))->toEqualCanonicalizing([
        'top_users',
        'current_user_position',
        'current_user_data',
        'period',
    ]);

    expect(array_keys($payload['top_users'][0]))->toEqualCanonicalizing([
        'id',
        'first_name',
        'last_name',
        'xp',
        'position',
        'is_current_user',
        'avatar_config',
    ]);
});

test('o model serializado esconde o avatar', function () {
    expect(userWithAvatar()->toArray())->not->toHaveKey('avatar_config');
});
