<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

// Perfil público e Configurações pela API do app. Os campos são os da web: o que sai do
// perfil é escolhido um a um (PublicProfileController), e as configurações são as mesmas de
// /settings/profile.

test('o perfil público pelo uuid traz só os campos da web', function () {
    $viewer = User::factory()->create();
    $other = User::factory()->create(['name' => 'Maria da Silva Souza']);
    $other->forceFill(['bio' => 'Estudo para a OAB.'])->save();
    Sanctum::actingAs($viewer);

    $payload = $this->getJson("/api/v1/profiles/{$other->uuid}")->assertOk()->json();

    expect(array_keys($payload))->toEqualCanonicalizing(['profile', 'achievements'])
        ->and($payload['profile'])->toMatchArray([
            'first_name' => 'Maria',
            'last_name' => 'Souza',
            'bio' => 'Estudo para a OAB.',
            'is_self' => false,
        ])
        ->and($payload['profile'])->not->toHaveKey('email');
});

test('o perfil não abre pelo id', function () {
    $other = User::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/profiles/{$other->id}")->assertNotFound();
});

test('o ranking traz o uuid para abrir o perfil', function () {
    $user = User::factory()->create();
    $user->addXp(10, 'play');
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/ranking')->assertJsonPath('top_users.0.uuid', $user->uuid);
});

test('a descrição é salva em uma linha, até 160 caracteres', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/profile', ['bio' => "  Concurseira\n  de   tribunal "])->assertOk();
    expect($user->fresh()->bio)->toBe('Concurseira de tribunal');

    $this->patchJson('/api/v1/profile', ['bio' => str_repeat('a', 161)])->assertUnprocessable();

    $this->patchJson('/api/v1/profile', ['bio' => ''])->assertOk();
    expect($user->fresh()->bio)->toBeNull();
});

test('as configurações trazem descrição, e-mails e conquistas, e salvam as escolhas', function () {
    $user = User::factory()->create();
    $user->forceFill(['bio' => 'Oi'])->save();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonPath('bio', 'Oi')
        ->assertJsonPath('profile_uuid', $user->uuid)
        ->assertJsonPath('receives_emails', true)
        ->assertJsonPath('achievements', []);

    $this->putJson('/api/v1/settings/emails', ['receives_emails' => false])
        ->assertOk()
        ->assertJsonPath('receives_emails', false);
    expect($user->fresh()->email_opt_out_at)->not->toBeNull();

    $this->putJson('/api/v1/settings/achievements', ['hidden' => []])->assertOk();
    $this->putJson('/api/v1/settings/achievements', ['hidden' => [999999]])->assertUnprocessable();
});
