<?php

use App\Models\EnergyTransaction;
use App\Models\User;
use App\Models\UserBetaCheckpoint;
use App\Models\UserSegmentProgress;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

// Energia do mapa novo (config/energy.php): as fases gastam, o combo devolve uma parte, a
// revisão custa como a fase (sem combo), o desafio gasta por questão, a primeira prova da etapa
// rende e o tempo recarrega +1 por hora. Mesmo acertando tudo, a energia acaba. Testado pela API do app, que usa os mesmos controllers da web (/beta/play).

function energyPlayer(int $energy = 25): User
{
    $user = User::factory()->createOne();
    $user->forceFill(['energy' => $energy, 'energy_updated_at' => $energy < 25 ? now() : null])->save();
    Sanctum::actingAs($user);

    return $user;
}

function energyComplete(User $user, $legislation, int $from, int $to): void
{
    foreach (array_slice(studyBlockIds($legislation), $from, $to - $from + 1) as $blockId) {
        UserSegmentProgress::create([
            'user_id' => $user->id,
            'legislation_segment_id' => $blockId,
            'correct_answers' => 1,
            'attempts' => 1,
            'best_score' => 100,
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }
}

function energyKey($legislation, int $block): string
{
    return App\Models\LegislationSegment::findOrFail(studyBlockIds($legislation)[$block])->uuid;
}

function energyAttempts($test, int $phase, bool $correct, int $times)
{
    $response = null;
    for ($i = 0; $i < $times; $i++) {
        $response = $test->postJson('/api/v1/trail/attempt', ['phase_id' => $phase, 'correct' => $correct])->assertOk();
    }

    return $response;
}

afterEach(fn () => Carbon::setTestNow());

test('o tempo recarrega +1 por hora até a barra cheia, e não passa dela', function () {
    Carbon::setTestNow('2026-11-21 10:00:00');
    $user = energyPlayer(20);
    $user->forceFill(['energy_updated_at' => now()->subHours(3)->subMinutes(20)])->save();

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('energy.value', 23)
        ->assertJsonPath('energy.max', 25)
        ->assertJsonPath('energy.infinite', false)
        ->assertJsonPath('energy.next_at', now()->addMinutes(40)->toIso8601String());

    Carbon::setTestNow(now()->addHours(10));
    $this->getJson('/api/v1/me')->assertJsonPath('energy.value', 25)->assertJsonPath('energy.next_at', null);

    // Acima da barra cheia (supercarga da prova) não recarrega nem perde com o tempo
    $user->forceFill(['energy' => 40, 'energy_updated_at' => now()->subHours(5)])->save();
    $this->getJson('/api/v1/me')->assertJsonPath('energy.value', 40);
});

test('na fase nova o certo custa 1, o errado 2, e 5 certos seguidos devolvem 1', function () {
    studyLegislation(30);
    $user = energyPlayer();

    $this->getJson('/api/v1/trail/phases/1')->assertOk()->assertJsonPath('isReview', false);

    energyAttempts($this, 1, true, 4);
    $this->postJson('/api/v1/trail/attempt', ['phase_id' => 1, 'correct' => true])
        ->assertJsonPath('combo', ['streak' => 5, 'gained' => 1])
        ->assertJsonPath('energy.value', 21);

    $this->postJson('/api/v1/trail/attempt', ['phase_id' => 1, 'correct' => false])
        ->assertJsonPath('energy.value', 19)
        ->assertJsonPath('combo', null);

    // A sequência recomeça depois do erro: o 5º certo depois dele é o próximo combo
    energyAttempts($this, 1, true, 4)->assertJsonPath('combo', null);

    expect($user->fresh()->energy)->toBe(15)
        ->and(EnergyTransaction::where('user_id', $user->id)->pluck('reason')->countBy()->all())
        ->toEqualCanonicalizing([EnergyTransaction::PHASE => 9, EnergyTransaction::COMBO => 1, EnergyTransaction::FAIL => 1]);
});

test('a recarga conta da hora em que a barra deixou de estar cheia', function () {
    Carbon::setTestNow('2026-11-21 10:00:00');
    studyLegislation(30);
    $user = energyPlayer();

    $this->getJson('/api/v1/trail/phases/1');
    $this->postJson('/api/v1/trail/attempt', ['phase_id' => 1, 'correct' => false]);

    expect($user->fresh()->energy_updated_at->toDateTimeString())->toBe('2026-11-21 10:00:00');

    Carbon::setTestNow('2026-11-21 11:30:00');
    $this->getJson('/api/v1/me')->assertJsonPath('energy.value', 24);
});

test('se a energia acaba no meio da fase nova a pessoa sai e o fim é recusado', function () {
    studyLegislation(30);
    $user = energyPlayer(10);

    $this->getJson('/api/v1/trail/phases/1')->assertOk();

    energyAttempts($this, 1, false, 4)->assertJsonPath('out_of_energy', false);
    $this->postJson('/api/v1/trail/attempt', ['phase_id' => 1, 'correct' => false])
        ->assertJsonPath('energy.value', 0)
        ->assertJsonPath('out_of_energy', true);

    $this->postJson('/api/v1/trail/finish', [
        'phase_id' => 1,
        'results' => [['segment_uuid' => energyKey(App\Models\Legislation::first(), 0), 'answers' => [], 'correct_count' => 1, 'total_count' => 1]],
    ])->assertStatus(409);

    expect(UserSegmentProgress::where('user_id', $user->id)->count())->toBe(0);

    // E a fase nova não abre de novo até ter 10
    $this->getJson('/api/v1/trail/phases/1')->assertForbidden()->assertJsonPath('code', 'no_energy');
});

test('a revisão custa como a fase nova, abre com 1 e não tem combo', function () {
    $legislation = studyLegislation(30);
    $user = energyPlayer(0);
    energyComplete($user, $legislation, 0, 9);

    $this->getJson('/api/v1/trail/phases/1')->assertForbidden()->assertJsonPath('code', 'no_energy');

    $user->forceFill(['energy' => 8])->save();
    $this->getJson('/api/v1/trail/phases/1')->assertOk()->assertJsonPath('isReview', true);

    // 5 certos seguidos na revisão custam 5 e não dão combo
    energyAttempts($this, 1, true, 5)
        ->assertJsonPath('combo', null)
        ->assertJsonPath('energy.value', 3);

    // Errar custa 2; zerada com bloco por fazer, a pessoa sai da revisão
    $this->postJson('/api/v1/trail/attempt', ['phase_id' => 1, 'correct' => false])->assertJsonPath('energy.value', 1);
    $this->postJson('/api/v1/trail/attempt', ['phase_id' => 1, 'correct' => true])
        ->assertJsonPath('energy.value', 0)
        ->assertJsonPath('out_of_energy', true);
});

test('a tela sem energia traz a energia e o anúncio', function () {
    energyPlayer(0);

    $this->getJson('/api/v1/trail/energy')
        ->assertOk()
        ->assertJsonPath('energy.value', 0)
        ->assertJsonPath('ad_reward', 5)
        ->assertJsonMissingPath('review');
});

test('o desafio do quadrado gasta 1 por questão nova e tira a pessoa quando a energia acaba', function () {
    $legislation = studyLegislation(170);
    $material = studyMaterial($legislation, 0, 39);
    $user = energyPlayer(0);
    energyComplete($user, $legislation, 0, 39);
    $key = energyKey($legislation, 39);

    $this->getJson("/api/v1/trail/challenges/{$key}")->assertForbidden()->assertJsonPath('code', 'no_energy');

    $user->forceFill(['energy' => 1])->save();
    $this->getJson("/api/v1/trail/challenges/{$key}")->assertOk()->assertJsonPath('energy.value', 1);

    $this->postJson("/api/v1/trail/challenges/{$key}/answer", ['material' => $material->uuid, 'position' => 1, 'answer' => 'certo'])
        ->assertOk()
        ->assertJsonPath('is_correct', true)
        ->assertJsonPath('energy.value', 0)
        ->assertJsonPath('out_of_energy', true);

    // Responder de novo a mesma questão não cobra outra vez
    $this->postJson("/api/v1/trail/challenges/{$key}/answer", ['material' => $material->uuid, 'position' => 1, 'answer' => 'certo'])
        ->assertJsonPath('energy.value', 0);
    expect(EnergyTransaction::where('reason', EnergyTransaction::CHALLENGE)->count())->toBe(1);
});

test('o Dia da prova rende por acerto e combo, completa a barra com nota alta e refazer não rende', function () {
    $legislation = studyLegislation(170);
    $first = studyMaterial($legislation, 0, 39);
    $second = studyMaterial($legislation, 40, 79);
    $user = energyPlayer(10);
    energyComplete($user, $legislation, 0, 159);
    foreach ([39 => $first, 79 => $second] as $last => $material) {
        UserBetaCheckpoint::create([
            'user_id' => $user->id,
            'legislation_segment_id' => studyBlockIds($legislation)[$last],
            'done_material_ids' => [$material->id],
        ]);
    }
    $key = energyKey($legislation, 159);

    $answerAll = function () use ($key) {
        $questions = $this->postJson("/api/v1/trail/exams/{$key}/start")->assertOk()->json('questions');
        $last = null;
        foreach ($questions as $question) {
            $last = $this->postJson("/api/v1/trail/exams/{$key}/answer", [
                'question' => $question['uuid'],
                'answer' => $question['type'] === 'true_false' ? 'certo' : 'C',
            ])->assertOk();
        }

        return $last;
    };

    // 4 acertos (+4) e um combo de 3 (+2) = 16; nota 100% completa a barra até 25
    $answerAll()
        ->assertJsonPath('finished', true)
        ->assertJsonPath('result.energy_gained', 15)
        ->assertJsonPath('result.user.energy.value', 25);

    // Refazer: custa 5 ao começar e não rende (nenhuma questão nova acertada e sem combo)
    $answerAll()->assertJsonPath('result.energy_gained', 0);
    expect($user->fresh()->energy)->toBe(20);
});

test('o Dia da prova pode passar da barra cheia até 50', function () {
    $legislation = studyLegislation(170);
    $first = studyMaterial($legislation, 0, 39);
    $second = studyMaterial($legislation, 40, 79);
    $user = energyPlayer();
    $user->forceFill(['energy' => 48])->save();
    energyComplete($user, $legislation, 0, 159);
    foreach ([39 => $first, 79 => $second] as $last => $material) {
        UserBetaCheckpoint::create([
            'user_id' => $user->id,
            'legislation_segment_id' => studyBlockIds($legislation)[$last],
            'done_material_ids' => [$material->id],
        ]);
    }
    $key = energyKey($legislation, 159);

    $questions = $this->postJson("/api/v1/trail/exams/{$key}/start")->json('questions');
    $this->postJson("/api/v1/trail/exams/{$key}/answer", [
        'question' => $questions[0]['uuid'],
        'answer' => $questions[0]['type'] === 'true_false' ? 'certo' : 'C',
    ])->assertJsonPath('energy', 1);

    $this->postJson("/api/v1/trail/exams/{$key}/finish")
        ->assertOk()
        ->assertJsonPath('energy_gained', 1)
        ->assertJsonPath('user.energy.value', 49);

    expect($user->fresh()->energy)->toBe(49);
});

test('o anúncio dá 5, sem passar da barra cheia', function () {
    $user = energyPlayer(10);

    $this->postJson('/api/v1/trail/energy/reward')->assertOk()->assertJsonPath('energy.value', 15);

    $user->forceFill(['energy' => 22])->save();
    $this->postJson('/api/v1/trail/energy/reward')->assertJsonPath('energy.value', 25);
});

test('quem assina tem energia infinita: abre sem energia e não gasta', function () {
    studyLegislation(30);
    $user = energyPlayer(0);
    giveActiveSubscription($user);

    $this->getJson('/api/v1/trail/phases/1')->assertOk()->assertJsonPath('energy.infinite', true);

    energyAttempts($this, 1, false, 3)
        ->assertJsonPath('energy.value', 0)
        ->assertJsonPath('out_of_energy', false);
    energyAttempts($this, 1, true, 5)->assertJsonPath('combo', null);

    expect(EnergyTransaction::where('user_id', $user->id)->count())->toBe(0);
});

test('na web a energia vem só para administradores', function () {
    $user = energyPlayer();
    $admin = studyAdmin();

    $this->actingAs($user)->get('/settings/profile')->assertInertia(fn ($page) => $page->where('auth.user.energy', null));
    $this->actingAs($admin)->get('/settings/profile')->assertInertia(fn ($page) => $page->where('auth.user.energy.value', 25));
});

test('o combo nunca paga o que os acertos custaram', function () {
    foreach (['phase', 'challenge'] as $kind) {
        $rules = config("energy.{$kind}");
        $cost = $rules['correct'] ?? $rules['question'];

        expect($rules['combo_gain'])->toBeLessThan($rules['combo_every'] * $cost);
    }

    expect(config('energy.review.correct'))->toBeGreaterThan(0)
        ->and(config('energy.challenge.question'))->toBeGreaterThan(0)
        ->and(config('energy.exam.retake'))->toBeGreaterThan(0);
});

test('acertando tudo, a energia acaba: na fase nova, na revisão, no desafio e na prova refeita', function () {
    $legislation = studyLegislation(170);
    $first = studyMaterial($legislation, 0, 39);
    $second = studyMaterial($legislation, 40, 79);
    $user = energyPlayer();

    // Fase nova sem errar nenhum bloco, de novo e de novo (sai sem terminar, então ela segue nova)
    $played = 0;
    while ($this->getJson('/api/v1/trail/phases/1')->status() === 200) {
        energyAttempts($this, 1, true, 10);
        expect(++$played)->toBeLessThan(10);
    }
    // Com a barra cheia, no máximo 2 fases: 25, 17, 9 e a 3ª não abre
    expect($played)->toBe(2)->and($user->fresh()->energy)->toBe(9);

    // Revisão sem errar: gasta 1 por bloco até a pessoa sair
    energyComplete($user, $legislation, 0, 159);
    foreach ([39 => $first, 79 => $second] as $last => $material) {
        UserBetaCheckpoint::create([
            'user_id' => $user->id,
            'legislation_segment_id' => studyBlockIds($legislation)[$last],
            'done_material_ids' => [$material->id],
        ]);
    }
    $this->getJson('/api/v1/trail/phases/1')->assertOk()->assertJsonPath('isReview', true);
    energyAttempts($this, 1, true, 9)->assertJsonPath('out_of_energy', true)->assertJsonPath('energy.value', 0);
    $this->getJson('/api/v1/trail/phases/1')->assertForbidden();

    // Desafio já feito, sempre certo: 1 por questão, sem combo
    $user->forceFill(['energy' => 3])->save();
    $key = energyKey($legislation, 39);
    $this->getJson("/api/v1/trail/challenges/{$key}")->assertOk();
    foreach ([1, 2, 1] as $position) {
        $last = $this->postJson("/api/v1/trail/challenges/{$key}/answer", [
            'material' => $first->uuid,
            'position' => $position,
            'answer' => $position === 1 ? 'certo' : 'C',
        ])->assertJsonPath('combo', null);
    }
    $last->assertJsonPath('energy.value', 0);
    $this->getJson("/api/v1/trail/challenges/{$key}")->assertForbidden()->assertJsonPath('code', 'no_energy');

    // Dia da prova: a primeira vez rende; refazer custa 5 e não rende
    $exam = energyKey($legislation, 159);
    $answerAll = function () use ($exam) {
        $questions = $this->postJson("/api/v1/trail/exams/{$exam}/start")->assertOk()->json('questions');
        foreach ($questions as $question) {
            $last = $this->postJson("/api/v1/trail/exams/{$exam}/answer", [
                'question' => $question['uuid'],
                'answer' => $question['type'] === 'true_false' ? 'certo' : 'C',
            ]);
        }

        return $last;
    };
    $answerAll()->assertJsonPath('result.energy_gained', 25);
    $retakes = 0;
    while ($this->postJson("/api/v1/trail/exams/{$exam}/start")->status() === 200) {
        $this->postJson("/api/v1/trail/exams/{$exam}/finish")->assertJsonPath('energy_gained', 0);
        expect(++$retakes)->toBeLessThan(10);
    }
    expect($retakes)->toBe(5)
        ->and($user->fresh()->energy)->toBe(0)
        ->and($this->postJson("/api/v1/trail/exams/{$exam}/start")->json('code'))->toBe('no_energy');
});

test('com a barra cheia, quem erra 7 vezes ainda termina a primeira fase', function () {
    studyLegislation(30);
    energyPlayer();

    $this->getJson('/api/v1/trail/phases/1')->assertOk();
    energyAttempts($this, 1, false, 7)->assertJsonPath('out_of_energy', false);
    energyAttempts($this, 1, true, 10)
        ->assertJsonPath('out_of_energy', false)
        ->assertJsonPath('energy.value', 3);
});
