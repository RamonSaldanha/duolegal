<?php

use App\Models\Legislation;
use App\Models\User;
use App\Models\UserLegislationSelection;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

function publishedLegislation(string $title): Legislation
{
    return Legislation::create(['title' => $title, 'raw_text' => 'Art. 1º', 'status' => 'published']);
}

test('cadastro pelo site leva ao onboarding', function () {
    $response = $this->post('/register', [
        'name' => 'Ana Souza',
        'email' => 'ana@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('onboarding.show', absolute: false));
});

test('cadastro pelo app antigo (sem o flag) não passa pelo onboarding nem expõe os campos novos', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'App User',
        'email' => 'app@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'device_name' => 'android',
    ]);

    $response->assertSuccessful();
    expect($response->json('user'))->not->toHaveKeys(['onboarding_answers', 'onboarding_completed_at', 'welcome_offer_expires_at']);

    // Já sai com o onboarding concluído: não cai nele nem ganha a oferta pelo site.
    $user = User::where('email', 'app@example.com')->first();
    expect($user->onboarding_completed_at)->not->toBeNull();
    expect($user->welcome_offer_expires_at)->toBeNull();

    $this->actingAs($user)->get(route('onboarding.show'))->assertRedirect(route('play.map'));
});

test('conta que já passou do onboarding não ganha XP nem oferta pela URL', function () {
    $user = User::factory()->create(['onboarding_completed_at' => now()->subMonth()]);

    $this->actingAs($user)->post(route('onboarding.challenge'))->assertRedirect(route('play.map'));
    $this->actingAs($user)->post(route('onboarding.offer'))->assertRedirect(route('play.map'));

    $user->refresh();
    expect($user->xp)->toBe(0);
    expect($user->welcome_offer_expires_at)->toBeNull();
});

test('o JSON cru do usuário na API segue sem os campos do onboarding', function () {
    $user = User::factory()->create([
        'onboarding_answers' => ['goal' => 'oab'],
        'onboarding_completed_at' => now(),
        'welcome_offer_expires_at' => now()->addDay(),
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonMissingPath('onboarding_answers')
        ->assertJsonMissingPath('onboarding_completed_at')
        ->assertJsonMissingPath('welcome_offer_expires_at');
});

test('a trilha salva as respostas e a seleção de legislações', function () {
    $user = User::factory()->create();
    $cf = publishedLegislation('Constituição Federal de 1988');
    $cp = publishedLegislation('Código Penal');
    $draft = Legislation::create(['title' => 'Rascunho', 'raw_text' => 'x', 'status' => 'draft']);

    $this->actingAs($user)
        ->post(route('onboarding.trail'), [
            'goal' => 'oab',
            'area' => 'penal',
            'minutes' => 30,
            'legislation_ids' => [$cf->id, $cp->id, $draft->id],
        ])
        ->assertRedirect();

    expect($user->fresh()->onboarding_answers)->toBe(['goal' => 'oab', 'area' => 'penal', 'minutes' => 30]);
    expect(UserLegislationSelection::where('user_id', $user->id)->pluck('legislation_id')->sort()->values()->all())
        ->toBe(collect([$cf->id, $cp->id])->sort()->values()->all());
});

test('a trilha rejeita respostas fora da lista', function () {
    $user = User::factory()->create();
    $cf = publishedLegislation('Constituição Federal de 1988');

    $this->actingAs($user)
        ->post(route('onboarding.trail'), ['goal' => 'outro', 'area' => 'penal', 'minutes' => 7, 'legislation_ids' => [$cf->id]])
        ->assertSessionHasErrors(['goal', 'minutes']);
});

test('o desafio dá XP de verdade uma vez só', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('onboarding.challenge'))->assertRedirect();
    $this->actingAs($user)->post(route('onboarding.challenge'))->assertRedirect();

    expect($user->fresh()->xp)->toBe(25);
    expect($user->xpTransactions()->where('source_type', 'onboarding')->count())->toBe(1);
});

test('a oferta vale 24h a partir da primeira vez que aparece', function () {
    Carbon::setTestNow('2026-10-14 10:00:00');
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('onboarding.offer'));
    expect($user->fresh()->welcome_offer_expires_at->toDateTimeString())->toBe('2026-10-15 10:00:00');

    // Ver a tela de novo não estica o prazo.
    Carbon::setTestNow('2026-10-14 20:00:00');
    $this->actingAs($user)->post(route('onboarding.offer'));
    expect($user->fresh()->welcome_offer_expires_at->toDateTimeString())->toBe('2026-10-15 10:00:00');
    expect($user->fresh()->hasWelcomeOffer())->toBeTrue();

    Carbon::setTestNow('2026-10-15 10:00:01');
    expect($user->fresh()->hasWelcomeOffer())->toBeFalse();
});

test('pular conclui o onboarding sem oferta e manda para o mapa', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.finish'), ['next' => 'map'])
        ->assertRedirect(route('play.map'));

    $user->refresh();
    expect($user->onboarding_completed_at)->not->toBeNull();
    expect($user->hasWelcomeOffer())->toBeFalse();

    $this->actingAs($user)->get(route('onboarding.show'))->assertRedirect(route('play.map'));
});

test('aceitar a oferta conclui e manda para a assinatura', function () {
    $user = User::factory()->create(['welcome_offer_expires_at' => now()->addHours(5)]);

    $this->actingAs($user)
        ->post(route('onboarding.finish'), ['next' => 'subscription'])
        ->assertRedirect(route('subscription.index'));
});
