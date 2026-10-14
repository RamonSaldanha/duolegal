<?php

use App\Models\Legislation;
use App\Models\User;
use App\Models\UserLegislationSelection;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

// Onboarding pelo app. As regras são as do site (OnboardingService); aqui o
// que se confere é que valem também por este caminho, e que o app antigo
// (que não manda `onboarding: true`) continua sem onboarding.

beforeEach(function () {
    // Sem plano anual configurado o preço não vai ao Stripe (e vem nulo).
    config(['subscription.plans.yearly' => null]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function apiLegislation(string $title, string $status = 'published'): Legislation
{
    return Legislation::create(['title' => $title, 'raw_text' => 'Art. 1º', 'status' => $status]);
}

function pendingOnboardingUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    Sanctum::actingAs($user);

    return $user;
}

function registerFromApp(array $extra = []): \Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/v1/auth/register', [
        'name' => 'App User',
        'email' => 'app@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        ...$extra,
    ]);
}

test('cadastro pelo app novo deixa o onboarding pendente', function () {
    registerFromApp(['onboarding' => true])
        ->assertCreated()
        ->assertJsonPath('user.needs_onboarding', true)
        ->assertJsonPath('user.welcome_offer', null);

    expect(User::where('email', 'app@example.com')->first()->onboarding_completed_at)->toBeNull();
});

test('cadastro pelo app antigo sai com o onboarding concluído', function () {
    registerFromApp()
        ->assertCreated()
        ->assertJsonPath('user.needs_onboarding', false);

    expect(User::where('email', 'app@example.com')->first()->onboarding_completed_at)->not->toBeNull();
});

test('GET /onboarding traz o que as telas precisam', function () {
    pendingOnboardingUser();
    $cf = apiLegislation('Constituição Federal de 1988');
    apiLegislation('Rascunho', 'draft');

    $this->getJson('/api/v1/onboarding')
        ->assertOk()
        ->assertJsonPath('completed', false)
        ->assertJsonPath('challenge_done', false)
        ->assertJsonPath('welcome_offer', null)
        ->assertJsonPath('legislations', [['id' => $cf->id, 'title' => $cf->title]]);
});

test('a trilha salva as respostas e só as leis publicadas', function () {
    $user = pendingOnboardingUser();
    $cf = apiLegislation('Constituição Federal de 1988');
    $cp = apiLegislation('Código Penal');
    $draft = apiLegislation('Rascunho', 'draft');

    $this->postJson('/api/v1/onboarding/trail', [
        'goal' => 'oab',
        'area' => 'penal',
        'minutes' => 30,
        'legislation_ids' => [$cf->id, $cp->id, $draft->id],
    ])->assertOk();

    expect($user->fresh()->onboarding_answers)->toBe(['goal' => 'oab', 'area' => 'penal', 'minutes' => 30]);
    expect(UserLegislationSelection::where('user_id', $user->id)->pluck('legislation_id')->sort()->values()->all())
        ->toBe(collect([$cf->id, $cp->id])->sort()->values()->all());
});

test('a trilha rejeita respostas fora da lista', function () {
    pendingOnboardingUser();
    $cf = apiLegislation('Constituição Federal de 1988');

    $this->postJson('/api/v1/onboarding/trail', ['goal' => 'outro', 'area' => 'penal', 'minutes' => 7, 'legislation_ids' => [$cf->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['goal', 'minutes']);
});

test('o desafio dá XP de verdade uma vez só e devolve o usuário', function () {
    $user = pendingOnboardingUser();

    $this->postJson('/api/v1/onboarding/challenge')
        ->assertOk()
        ->assertJsonPath('xp_gained', 25)
        ->assertJsonPath('user.xp', 25);

    $this->postJson('/api/v1/onboarding/challenge')
        ->assertOk()
        ->assertJsonPath('xp_gained', 0);

    expect($user->fresh()->xp)->toBe(25);
    $this->getJson('/api/v1/onboarding')->assertJsonPath('challenge_done', true);
});

test('a oferta vale 24h a partir da primeira vez que aparece', function () {
    Carbon::setTestNow('2026-10-14 10:00:00');
    $user = pendingOnboardingUser();

    $this->postJson('/api/v1/onboarding/offer')
        ->assertOk()
        ->assertJsonPath('welcome_offer.percent_off', 15);
    expect($user->fresh()->welcome_offer_expires_at->toDateTimeString())->toBe('2026-10-15 10:00:00');

    // Ver a tela de novo não estica o prazo.
    Carbon::setTestNow('2026-10-14 20:00:00');
    $this->postJson('/api/v1/onboarding/offer')->assertOk();
    expect($user->fresh()->welcome_offer_expires_at->toDateTimeString())->toBe('2026-10-15 10:00:00');

    // O /me leva a oferta para o banner do mapa.
    $this->getJson('/api/v1/me')->assertJsonPath('welcome_offer.percent_off', 15);
});

test('concluir encerra o onboarding e devolve o usuário com a oferta', function () {
    $user = pendingOnboardingUser(['welcome_offer_expires_at' => now()->addHours(5)]);

    $this->postJson('/api/v1/onboarding/finish')
        ->assertOk()
        ->assertJsonPath('user.needs_onboarding', false)
        ->assertJsonPath('user.welcome_offer.percent_off', 15);

    expect($user->fresh()->onboarding_completed_at)->not->toBeNull();
});

test('pular antes da oferta conclui sem oferta', function () {
    $user = pendingOnboardingUser();

    $this->postJson('/api/v1/onboarding/finish')
        ->assertOk()
        ->assertJsonPath('user.welcome_offer', null);

    expect($user->fresh()->hasWelcomeOffer())->toBeFalse();
});

test('depois de concluído, nada do onboarding vale de novo', function () {
    $user = pendingOnboardingUser(['onboarding_completed_at' => now()->subDay()]);
    $cf = apiLegislation('Constituição Federal de 1988');

    $this->getJson('/api/v1/onboarding')->assertOk()->assertJsonPath('completed', true);
    $this->postJson('/api/v1/onboarding/trail', ['goal' => 'oab', 'area' => 'penal', 'minutes' => 5, 'legislation_ids' => [$cf->id]])
        ->assertStatus(409);
    $this->postJson('/api/v1/onboarding/challenge')->assertStatus(409);
    $this->postJson('/api/v1/onboarding/offer')->assertStatus(409);

    $user->refresh();
    expect($user->xp)->toBe(0);
    expect($user->welcome_offer_expires_at)->toBeNull();
    expect($user->onboarding_answers)->toBeNull();
});
