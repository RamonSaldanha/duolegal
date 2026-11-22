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

test('o mapa é o padrão da web para qualquer aluno; os endereços do beta levam a ele', function () {
    betaLegislation(10);
    $user = User::factory()->createOne(['lives' => 5]);

    $this->actingAs($user)->get('/play')->assertOk()->assertInertia(fn ($page) => $page->component('BetaPlay/Map'));
    $this->actingAs($user)->get('/play/phase/1')->assertOk()->assertInertia(fn ($page) => $page->component('BetaPlay/Phase'));
    $this->actingAs($user)->postJson('/play/attempt', ['phase_id' => 1, 'correct' => false])->assertOk()->assertJsonPath('energy.value', 23);

    $this->actingAs($user)->get('/beta/play')->assertRedirect('/play');
    $this->actingAs($user)->get('/beta/play/phase/1')->assertRedirect('/play/phase/1');

    // O mapa anterior fica só pelo endereço; a prévia segue só para administradores
    $this->actingAs($user)->get('/legado/leis')->assertOk()->assertInertia(fn ($page) => $page->component('LegislationPlay/Map'));
    $this->actingAs($user)->get('/play/previa/fim')->assertRedirect(route('play.map'));
});

test('o mapa beta junta 4 fases de 10 blocos em cada quadrado', function () {
    betaLegislation(85);
    $user = betaAdmin();

    $this->actingAs($user)->get('/play')
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

    $this->actingAs($user)->get('/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('BetaPlay/Map')
            ->has('welcomeOffer.percent_off')
            ->has('welcomeOffer.expires_at'));

    $user->forceFill(['welcome_offer_expires_at' => now()->subMinute()])->save();

    $this->actingAs($user)->get('/play')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('welcomeOffer', null));
});

test('a fase beta apresenta 10 desafios', function () {
    betaLegislation(25);
    $user = betaAdmin(['lives' => 5]);

    $this->actingAs($user)->get('/play/phase/1')
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('BetaPlay/Phase')
            ->has('challenges', BetaPlayController::BETA_BLOCKS_PER_PHASE)
            ->has('phaseBlocks', BetaPlayController::BETA_BLOCKS_PER_PHASE)
            ->has('earlierBlocks', 0)
            ->where('hasMoreAbove', false)
            ->where('phaseNumber', 1));

    // Fase bloqueada não abre
    $this->actingAs($user)->get('/play/phase/2')->assertRedirect('/play');
});

test('terminar a fase grava os blocos e dá o XP de uma vez', function () {
    $legislation = betaLegislation(25);
    $user = betaAdmin();
    $phaseBlocks = $legislation->segments()->orderBy('char_start')->limit(10)->get();

    $response = $this->actingAs($user)->postJson('/play/finish', [
        'phase_id' => 1,
        'results' => betaResults($phaseBlocks),
    ]);

    $response->assertSuccessful()->assertJsonPath('xp_gained', 100);

    expect(UserSegmentProgress::where('user_id', $user->id)->where('is_completed', true)->count())->toBe(10)
        ->and($user->fresh()->xp)->toBe(100);

    // A fase seguinte passa a ser a atual, dentro do mesmo quadrado
    $this->actingAs($user)->get('/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.groups.0.completed_phases', 1)
            ->where('sections.0.groups.0.current_phase_id', 2));

    // Refazer a fase (revisão) não dá XP de novo
    $this->actingAs($user)->postJson('/play/finish', [
        'phase_id' => 1,
        'results' => betaResults($phaseBlocks),
    ])->assertSuccessful()->assertJsonPath('xp_gained', 0);
});

test('fase pela metade não grava nada', function () {
    $legislation = betaLegislation(25);
    $user = betaAdmin();
    $phaseBlocks = $legislation->segments()->orderBy('char_start')->limit(10)->get();

    // Faltam desafios
    $this->actingAs($user)->postJson('/play/finish', [
        'phase_id' => 1,
        'results' => betaResults($phaseBlocks->take(6)),
    ])->assertStatus(422);

    // Todos enviados, mas errados
    $this->actingAs($user)->postJson('/play/finish', [
        'phase_id' => 1,
        'results' => betaResults($phaseBlocks, false),
    ])->assertStatus(422);

    expect(UserSegmentProgress::where('user_id', $user->id)->count())->toBe(0)
        ->and($user->fresh()->xp)->toBe(0);
});

test('errar um desafio gasta a energia na hora, sem gravar progresso nem tirar vida', function () {
    betaLegislation(10);
    $user = betaAdmin(['lives' => 5]);

    $this->actingAs($user)->get('/play/phase/1')->assertOk();
    $this->actingAs($user)->postJson('/play/attempt', ['phase_id' => 1, 'correct' => false])
        ->assertSuccessful()
        ->assertJsonPath('energy.value', 23)
        ->assertJsonPath('out_of_energy', false);

    expect(UserSegmentProgress::where('user_id', $user->id)->count())->toBe(0)
        ->and($user->fresh()->lives)->toBe(5);
});

test('dispositivo vetado ou revogado continua sendo desafio, mas não dá XP', function () {
    $legislation = betaLegislation(25);
    $user = betaAdmin(['lives' => 5]);
    $blocks = $legislation->segments()->orderBy('char_start')->get();

    // A importação tira o texto e a marca: sobra só o rótulo
    $blocks[2]->update(['original_text' => "IX-A -;\u{00A0}\u{00A0}"]);
    $blocks[5]->update(['original_text' => 'Art. 194.']);

    $this->actingAs($user)->get('/play/phase/1')
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('challenges', 10)
            ->where('challenges.2.historical_label', 'IX-A')
            ->where('challenges.5.historical_label', 'Art. 194')
            ->where('challenges.0.historical_label', null)
            ->where('phaseBlocks.2.historical_label', 'IX-A'));

    // Os dez precisam ser vencidos; só os oito com texto dão XP
    $this->actingAs($user)->postJson('/play/finish', [
        'phase_id' => 1,
        'results' => betaResults($blocks->take(10)),
    ])->assertSuccessful()->assertJsonPath('xp_gained', 80);

    expect(UserSegmentProgress::where('user_id', $user->id)->where('is_completed', true)->count())->toBe(10);
});

test('o rótulo histórico só aparece em bloco sem texto', function (string $text, ?string $label) {
    expect((new LegislationSegment(['original_text' => $text]))->historicalLabel())->toBe($label);
})->with([
    ["IX-A -;\u{00A0}\u{00A0}", 'IX-A'],
    ['Art. 1.463.', 'Art. 1.463'],
    ['Art. 819-A.', 'Art. 819-A'],
    ['§ 3º  .', '§ 3º'],
    ['Parágrafo único.', 'Parágrafo único'],
    ['a) ;', 'a)'],
    ['IV - os pródigos.', null],
    ['II - ano;', null],
    ['Art. 569. Cabe:', null],
    ['§ 1º Em um ano:', null],
]);
