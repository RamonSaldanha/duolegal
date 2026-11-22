<?php

use App\Models\Legislation;
use App\Models\LegislationSegment;
use App\Models\SegmentLacuna;
use App\Models\User;
use App\Models\UserBetaCheckpoint;
use App\Models\UserSegmentProgress;
use App\Services\StudyMaterialService;
use Laravel\Sanctum\Sanctum;

// Mapa novo pela API do app (/api/v1/trail). As regras são as da web (testadas em
// BetaPlayTest, BetaCheckpointTest e BetaExamTest); aqui se confere que o app recebe os
// mesmos dados, que as recusas chegam em JSON com código e que qualquer aluno joga pelo app
// (na web, o /beta/play segue só para administradores).

/** Lei com $blocks blocos de 1 lacuna cada (a fase precisa de lacuna para ser vencida). */
function trailLegislation(int $blocks): Legislation
{
    $legislation = Legislation::create(['title' => 'Lei do App', 'raw_text' => 'Art.', 'status' => 'published']);

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

/** Quem joga pelo app nos testes: um aluno comum, com a energia cheia. */
function trailPlayer(array $attributes = []): User
{
    $user = User::factory()->createOne($attributes + ['lives' => 5]);
    Sanctum::actingAs($user);

    return $user;
}

/** Marca como concluídos os blocos de $from a $to (posições na lei). */
function trailComplete(User $user, Legislation $legislation, int $from, int $to): void
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

function trailKey(Legislation $legislation, int $block): string
{
    return LegislationSegment::findOrFail(studyBlockIds($legislation)[$block])->uuid;
}

test('qualquer aluno joga o mapa novo pelo app; na web ele segue só para administradores', function () {
    trailLegislation(10);
    $user = trailPlayer();

    $this->getJson('/api/v1/trail')->assertOk();
    $this->actingAs($user)->get('/beta/play')->assertRedirect(route('play.map'));
});

test('o mapa novo pelo app exige login', function () {
    $this->getJson('/api/v1/trail')->assertUnauthorized();
});

test('o mapa chega com etapas, quadrados, desafio e prova, sem campos internos', function () {
    $legislation = studyLegislation(170);
    studyMaterial($legislation, 0, 39);
    trailPlayer();

    $payload = $this->getJson('/api/v1/trail')
        ->assertOk()
        ->assertJsonPath('hasMoreAbove', false)
        ->assertJsonPath('sections.0.groups.0.total_steps', 5)
        ->assertJsonPath('sections.0.groups.0.challenge.state', 'locked')
        ->assertJsonPath('sections.0.groups.0.challenge.key', trailKey($legislation, 39))
        ->assertJsonPath('sections.0.exam.state', 'locked')
        ->assertJsonMissingPath('sections.0.groups.0.challenge.material_ids')
        ->assertJsonMissingPath('sections.0.exam.anchor_block_id')
        ->json();

    expect($payload['sections'])->toHaveCount(2)
        ->and($payload['sections'][0]['groups'])->toHaveCount(4)
        ->and($payload['sections'][0]['groups'][0]['state'])->toBe('current');
});

test('a fase atual abre com os desafios; a bloqueada responde unavailable', function () {
    trailLegislation(30);
    trailPlayer();

    $groups = $this->getJson('/api/v1/trail')->json('sections.0.groups');
    $current = $groups[0]['current_phase_id'];
    $blocked = $groups[0]['phases'][1]['id'];

    $this->getJson("/api/v1/trail/phases/{$current}")
        ->assertOk()
        ->assertJsonPath('phaseId', $current)
        ->assertJsonPath('phaseNumber', 1)
        ->assertJsonPath('isReview', false)
        ->assertJsonCount(10, 'challenges')
        ->assertJsonCount(10, 'phaseBlocks');

    $this->getJson("/api/v1/trail/phases/{$blocked}")
        ->assertStatus(409)
        ->assertJsonPath('code', 'unavailable');
});

test('sem a energia mínima a fase nova responde no_energy', function () {
    trailLegislation(10);
    $user = trailPlayer();
    $current = $this->getJson('/api/v1/trail')->json('sections.0.groups.0.current_phase_id');

    $user->forceFill(['energy' => 9, 'energy_updated_at' => now()])->save();

    $this->getJson("/api/v1/trail/phases/{$current}")
        ->assertForbidden()
        ->assertJsonPath('code', 'no_energy');
});

test('cada bloco verificado gasta energia na hora; as vidas não mudam mais', function () {
    trailLegislation(10);
    $user = trailPlayer(['lives' => 1]);
    $current = $this->getJson('/api/v1/trail')->json('sections.0.groups.0.current_phase_id');
    $this->getJson("/api/v1/trail/phases/{$current}")->assertOk()->assertJsonPath('energy.value', 25);

    $this->postJson('/api/v1/trail/attempt', ['phase_id' => $current, 'correct' => true])
        ->assertOk()
        ->assertJsonPath('energy.value', 24)
        ->assertJsonPath('combo', null)
        ->assertJsonPath('out_of_energy', false);

    // O build antigo (sem energia) ainda chama /fail: conta como erro na fase em jogo
    $this->postJson('/api/v1/trail/fail')
        ->assertOk()
        ->assertJsonPath('lost_life', false)
        ->assertJsonPath('lives', 1)
        ->assertJsonPath('out_of_lives', false);

    expect($user->fresh()->energy)->toBe(22)
        ->and($user->fresh()->lives)->toBe(1);
});

test('o fim da fase grava os blocos e dá XP', function () {
    $legislation = trailLegislation(10);
    $user = trailPlayer();
    $current = $this->getJson('/api/v1/trail')->json('sections.0.groups.0.current_phase_id');

    $results = LegislationSegment::where('legislation_id', $legislation->id)->orderBy('char_start')->get()
        ->map(fn ($segment) => [
            'segment_uuid' => $segment->uuid,
            'answers' => [['gap_order' => 1, 'word_position' => 3, 'correct_word' => 'União', 'user_word' => 'União', 'is_correct' => true]],
            'correct_count' => 1,
            'total_count' => 1,
        ])->all();

    $this->postJson('/api/v1/trail/finish', ['phase_id' => $current, 'results' => $results])
        ->assertOk()
        ->assertJsonPath('xp_gained', 100);

    expect(UserSegmentProgress::where('user_id', $user->id)->where('is_completed', true)->count())->toBe(10);
});

test('o desafio do quadrado chega com o resumo em blocos e sem gabarito', function () {
    $legislation = studyLegislation(170);
    $material = studyMaterial($legislation, 0, 39);
    $user = trailPlayer();
    trailComplete($user, $legislation, 0, 39);
    $key = trailKey($legislation, 39);

    $this->getJson("/api/v1/trail/challenges/{$key}")
        ->assertOk()
        ->assertJsonPath('checkpoint.key', $key)
        ->assertJsonPath('materials.0.uuid', $material->uuid)
        ->assertJsonPath('materials.0.summary_blocks.0', ['type' => 'heading', 'level' => 2, 'inlines' => [['text' => 'Resumo']]])
        ->assertJsonPath('materials.0.summary_blocks.1.inlines', [['text' => 'Texto '], ['text' => 'importante', 'bold' => true], ['text' => '.']])
        ->assertJsonMissingPath('materials.0.questions.0.correct_answer')
        ->assertJsonMissingPath('materials.0.questions.0.explanation');

    $this->postJson("/api/v1/trail/challenges/{$key}/summary", ['material' => $material->uuid])->assertOk();

    $this->postJson("/api/v1/trail/challenges/{$key}/answer", ['material' => $material->uuid, 'position' => 1, 'answer' => 'certo'])
        ->assertOk()
        ->assertJsonPath('is_correct', true)
        ->assertJsonPath('correct_answer', 'certo');
});

test('desafio com fases por fazer responde unavailable', function () {
    $legislation = studyLegislation(170);
    studyMaterial($legislation, 0, 39);
    trailPlayer();

    $this->getJson('/api/v1/trail/challenges/'.trailKey($legislation, 39))
        ->assertStatus(409)
        ->assertJsonPath('code', 'unavailable');
});

test('o Dia da prova abre, começa e corrige pela API', function () {
    $legislation = studyLegislation(170);
    $material = studyMaterial($legislation, 0, 39);
    $user = trailPlayer();
    trailComplete($user, $legislation, 0, 159);
    UserBetaCheckpoint::create([
        'user_id' => $user->id,
        'legislation_segment_id' => studyBlockIds($legislation)[39],
        'done_material_ids' => [$material->id],
    ]);
    $key = trailKey($legislation, 159);

    $this->getJson("/api/v1/trail/exams/{$key}")
        ->assertOk()
        ->assertJsonPath('exam.questions', 2)
        ->assertJsonPath('exam.seconds', 15 + 35)
        ->assertJsonPath('attempt', null);

    $attempt = $this->postJson("/api/v1/trail/exams/{$key}/start")->assertOk()->json();
    expect($attempt['questions'])->toHaveCount(2)
        ->and($attempt['questions'][0])->not->toHaveKey('correct_answer');

    $question = $attempt['questions'][0];
    $this->postJson("/api/v1/trail/exams/{$key}/answer", [
        'question' => $question['uuid'],
        'answer' => $question['type'] === 'true_false' ? 'certo' : 'C',
    ])->assertOk()->assertJsonPath('is_correct', true)->assertJsonPath('xp', 15);

    $this->postJson("/api/v1/trail/exams/{$key}/finish")
        ->assertOk()
        ->assertJsonPath('correct', 1)
        ->assertJsonPath('xp_gained', 15);
});

test('o resumo em blocos traz listas, citações e tabelas, sem HTML embutido', function () {
    $blocks = app(StudyMaterialService::class)->summaryBlocks(
        "Antes <b>negrito</b> *depois*\n\n- um\n- dois\n\n> **Caso.** Bruno.\n\n| A | B |\n|---|---|\n| 1 | 2 |\n\n---"
    );

    expect($blocks)->toBe([
        ['type' => 'paragraph', 'inlines' => [['text' => 'Antes negrito '], ['text' => 'depois', 'italic' => true]]],
        ['type' => 'list', 'ordered' => false, 'start' => 1, 'items' => [
            [['type' => 'paragraph', 'inlines' => [['text' => 'um']]]],
            [['type' => 'paragraph', 'inlines' => [['text' => 'dois']]]],
        ]],
        ['type' => 'quote', 'blocks' => [
            ['type' => 'paragraph', 'inlines' => [['text' => 'Caso.', 'bold' => true], ['text' => ' Bruno.']]],
        ]],
        ['type' => 'table', 'head' => [[['text' => 'A']], [['text' => 'B']]], 'rows' => [[[['text' => '1']], [['text' => '2']]]]],
        ['type' => 'rule'],
    ]);
});
