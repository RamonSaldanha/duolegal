<?php

use App\Http\Controllers\BetaPlayController;
use App\Models\Legislation;
use App\Models\LegislationSegment;
use App\Models\SegmentLacuna;
use App\Models\User;
use App\Models\UserSegmentProgress;
use Inertia\Testing\AssertableInertia;

/** Lei com $blocks blocos de 1 lacuna cada. */
function betaLegislation(int $blocks): Legislation
{
    $legislation = Legislation::create(['title' => 'Lei Beta', 'raw_text' => 'Art.', 'status' => 'published']);

    for ($i = 0; $i < $blocks; $i++) {
        $segment = LegislationSegment::create([
            'legislation_id' => $legislation->id,
            'original_text' => "Art. {$i} A União zelará.",
            'char_start' => $i * 100,
            'char_end' => $i * 100 + 24,
            'segment_type' => 'caput',
            'structural_marker' => "Art. {$i}",
            'position' => $i + 1,
            'is_block' => true,
        ]);

        SegmentLacuna::create([
            'legislation_segment_id' => $segment->id,
            'word' => 'União',
            'word_position' => 3,
            'is_correct' => true,
            'gap_order' => 1,
        ]);
    }

    return $legislation;
}

/** O mapa beta só abre para administradores. */
function betaAdmin(array $attributes = []): User
{
    $user = User::factory()->createOne($attributes);
    $user->forceFill(['is_admin' => true])->save();

    return $user;
}

function betaResults($segments, bool $correct = true): array
{
    return $segments->map(fn ($segment) => [
        'segment_uuid' => $segment->uuid,
        'answers' => [
            ['gap_order' => 1, 'word_position' => 3, 'correct_word' => 'União', 'user_word' => $correct ? 'União' : 'Estado', 'is_correct' => $correct],
        ],
        'correct_count' => $correct ? 1 : 0,
        'total_count' => 1,
    ])->values()->all();
}

test('o mapa beta só abre para administradores', function () {
    betaLegislation(10);
    $user = User::factory()->createOne(['lives' => 5]);

    $this->actingAs($user)->get('/beta/play')->assertRedirect(route('play.map'));
    $this->actingAs($user)->get('/beta/play/phase/1')->assertRedirect(route('play.map'));
    $this->actingAs($user)->postJson('/beta/play/fail')->assertRedirect(route('play.map'));

    expect($user->fresh()->lives)->toBe(5);
});

test('o mapa beta junta 4 fases de 10 blocos em cada quadrado', function () {
    betaLegislation(85);
    $user = betaAdmin();

    $this->actingAs($user)->get('/beta/play')
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('BetaPlay/Map')
            ->has('sections', 1)
            ->where('sections.0.total_phases', 9)
            ->has('sections.0.groups', 3)
            ->where('sections.0.groups.0.state', 'current')
            ->where('sections.0.groups.0.total_phases', BetaPlayController::PHASES_PER_GROUP)
            ->where('sections.0.groups.0.completed_phases', 0)
            ->where('sections.0.groups.1.state', 'locked')
            ->where('sections.0.groups.2.total_phases', 1));
});

test('o mapa beta recebe a oferta de boas-vindas enquanto ela vale', function () {
    betaLegislation(10);
    $user = betaAdmin();
    $user->forceFill(['welcome_offer_expires_at' => now()->addHours(20)])->save();

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('BetaPlay/Map')
            ->has('welcomeOffer.percent_off')
            ->has('welcomeOffer.expires_at'));

    $user->forceFill(['welcome_offer_expires_at' => now()->subMinute()])->save();

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('welcomeOffer', null));
});

test('a fase beta apresenta 10 desafios', function () {
    betaLegislation(25);
    $user = betaAdmin(['lives' => 5]);

    $this->actingAs($user)->get('/beta/play/phase/1')
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('BetaPlay/Phase')
            ->has('challenges', BetaPlayController::BETA_BLOCKS_PER_PHASE)
            ->has('phaseBlocks', BetaPlayController::BETA_BLOCKS_PER_PHASE)
            ->has('earlierBlocks', 0)
            ->where('hasMoreAbove', false)
            ->where('phaseNumber', 1));

    // Fase bloqueada não abre
    $this->actingAs($user)->get('/beta/play/phase/2')->assertRedirect('/beta/play');
});

test('terminar a fase grava os blocos e dá o XP de uma vez', function () {
    $legislation = betaLegislation(25);
    $user = betaAdmin();
    $phaseBlocks = $legislation->segments()->orderBy('char_start')->limit(10)->get();

    $response = $this->actingAs($user)->postJson('/beta/play/finish', [
        'phase_id' => 1,
        'results' => betaResults($phaseBlocks),
    ]);

    $response->assertSuccessful()->assertJsonPath('xp_gained', 100);

    expect(UserSegmentProgress::where('user_id', $user->id)->where('is_completed', true)->count())->toBe(10)
        ->and($user->fresh()->xp)->toBe(100);

    // A fase seguinte passa a ser a atual, dentro do mesmo quadrado
    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.groups.0.completed_phases', 1)
            ->where('sections.0.groups.0.current_phase_id', 2));

    // Refazer a fase (revisão) não dá XP de novo
    $this->actingAs($user)->postJson('/beta/play/finish', [
        'phase_id' => 1,
        'results' => betaResults($phaseBlocks),
    ])->assertSuccessful()->assertJsonPath('xp_gained', 0);
});

test('fase pela metade não grava nada', function () {
    $legislation = betaLegislation(25);
    $user = betaAdmin();
    $phaseBlocks = $legislation->segments()->orderBy('char_start')->limit(10)->get();

    // Faltam desafios
    $this->actingAs($user)->postJson('/beta/play/finish', [
        'phase_id' => 1,
        'results' => betaResults($phaseBlocks->take(6)),
    ])->assertStatus(422);

    // Todos enviados, mas errados
    $this->actingAs($user)->postJson('/beta/play/finish', [
        'phase_id' => 1,
        'results' => betaResults($phaseBlocks, false),
    ])->assertStatus(422);

    expect(UserSegmentProgress::where('user_id', $user->id)->count())->toBe(0)
        ->and($user->fresh()->xp)->toBe(0);
});

test('errar um desafio tira a vida na hora, sem gravar progresso', function () {
    betaLegislation(10);
    $user = betaAdmin(['lives' => 1]);

    $this->actingAs($user)->postJson('/beta/play/fail')
        ->assertSuccessful()
        ->assertJsonPath('lost_life', true)
        ->assertJsonPath('lives', 0)
        ->assertJsonPath('redirect_url', route('play.nolives'));

    expect(UserSegmentProgress::where('user_id', $user->id)->count())->toBe(0);
});
