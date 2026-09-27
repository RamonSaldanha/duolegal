<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// Avatar pela API do app. A validação é a mesma do editor da web
// (AvatarUpdateRequest); aqui o que se confere é que ela vale também por este
// caminho, e que o app recebe o avatar do jeito que a web recebe.

test('o app salva o avatar e recebe o usuário de volta', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    // Segunda opção de cada grupo, para o teste não depender dos nomes dos ids.
    $avatar = validAvatar(['hair' => config('avatar.shapes.hair')[1], 'background' => '#F0A93F']);

    $payload = $this->putJson('/api/v1/profile/avatar', ['avatar' => $avatar])
        ->assertOk()
        ->json();

    expect($payload['id'])->toBe($user->id);
    expect($payload['avatar_config'])->toEqualCanonicalizing($avatar);
    expect($user->fresh()->avatar_config)->toEqualCanonicalizing($avatar);
});

test('pela API, id de peça fora da lista é rejeitado', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/profile/avatar', ['avatar' => validAvatar(['body' => 'musculoso'])])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('avatar.body');

    expect($user->fresh()->avatar_config)->toBeNull();
});

test('pela API, cor malformada é rejeitada', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/profile/avatar', ['avatar' => validAvatar(['skin' => '"/><script>alert(1)</script>'])])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('avatar.skin');

    expect($user->fresh()->avatar_config)->toBeNull();
});

test('pela API, avatar incompleto é rejeitado', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->putJson('/api/v1/profile/avatar', ['avatar' => ['skin' => '#ABCDEF']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('avatar.body');
});

test('salvar o avatar pela API exige login', function () {
    $this->putJson('/api/v1/profile/avatar', ['avatar' => validAvatar()])->assertUnauthorized();
});

test('pela API, fantasia exclusiva é recusada para quem não assina', function (string $outfit) {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/profile/avatar', ['avatar' => validAvatar(['outfit' => $outfit])])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('avatar.outfit');

    expect($user->fresh()->avatar_config)->toBeNull();
})->with('fantasias premium');

test('pela API, assinante pode vestir a fantasia', function (string $outfit) {
    $user = User::factory()->create();
    giveActiveSubscription($user);
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/profile/avatar', ['avatar' => validAvatar(['outfit' => $outfit])])
        ->assertOk()
        ->assertJsonPath('avatar_config.outfit', $outfit);
})->with('fantasias premium');

test('/me devolve nulo para quem nunca editou o avatar', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('avatar_config', null);
});

test('/me tira a fantasia de quem deixou de assinar, sem apagá-la', function () {
    $user = User::factory()->create();
    $user->avatar_config = validAvatar(['outfit' => 'toga']);
    $user->save();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me')->assertJsonPath('avatar_config.outfit', 'nenhum');

    expect($user->fresh()->avatar_config['outfit'])->toBe('toga');
});

test('o ranking da API traz o avatar de cada um, sem fantasia de quem não assina', function () {
    $subscriber = User::factory()->create();
    giveActiveSubscription($subscriber);
    $subscriber->avatar_config = validAvatar(['outfit' => 'astronauta']);
    $subscriber->save();
    $subscriber->addXp(50, 'play');

    $lapsed = User::factory()->create();
    $lapsed->avatar_config = validAvatar(['outfit' => 'toga']);
    $lapsed->save();
    $lapsed->addXp(30, 'play');

    $withoutAvatar = User::factory()->create();
    $withoutAvatar->addXp(10, 'play');

    Sanctum::actingAs($withoutAvatar);

    $topUsers = collect($this->getJson('/api/v1/ranking')->assertOk()->json('top_users'))->keyBy('id');

    expect($topUsers[$subscriber->id]['avatar_config']['outfit'])->toBe('astronauta');
    expect($topUsers[$lapsed->id]['avatar_config']['outfit'])->toBe('nenhum');
    expect($topUsers[$withoutAvatar->id]['avatar_config'])->toBeNull();
});
