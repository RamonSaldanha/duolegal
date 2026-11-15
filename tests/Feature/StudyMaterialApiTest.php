<?php

use App\Http\Middleware\StudyMaterialAgent;
use App\Models\LegislationSegment;
use App\Models\StudyMaterial;
use App\Models\User;

const STUDY_API = '/api/v1/admin/study-materials';

function agentToken(?User $user = null): string
{
    return ($user ?? studyAdmin())->createToken('agente', [StudyMaterialAgent::ABILITY])->plainTextToken;
}

function agentPayload($legislation, int $from, int $to, array $overrides = []): array
{
    $range = array_slice(studyBlockIds($legislation), $from, $to - $from + 1);

    return array_merge([
        'legislation' => $legislation->uuid,
        'first_block_id' => $range[0],
        'last_block_id' => end($range),
        'block_ids' => $range,
        'title' => 'Organização da União',
        'summary' => "## Organização\n\nA **União** zela.",
        'generated_by_model' => 'claude-teste',
        'skill_version' => '1.0.0',
        'questions' => studyQuestions(),
    ], $overrides);
}

test('sem token as rotas do agente respondem 401 em JSON', function () {
    // Sem o cabeçalho Accept, como um curl simples
    $this->get(STUDY_API.'/legislations')->assertStatus(401)->assertJsonStructure(['message']);
    $this->post(STUDY_API, [])->assertStatus(401);
});

test('token sem a habilidade não entra, nem o token do app de um admin', function () {
    $admin = studyAdmin();

    $semHabilidade = $admin->createToken('outro', ['outra-coisa'])->plainTextToken;
    $this->withToken($semHabilidade)->getJson(STUDY_API.'/legislations')->assertStatus(403);

    // O token do app tem "*": passaria em tokenCan(), mas aqui não serve
    $mobile = $admin->createToken('mobile')->plainTextToken;
    $this->withToken($mobile)->getJson(STUDY_API.'/legislations')->assertStatus(403);
});

test('usuário que não é admin não entra mesmo com a habilidade', function () {
    $user = User::factory()->createOne();

    $this->withToken(agentToken($user))->getJson(STUDY_API.'/legislations')->assertStatus(403);
});

test('lista as leis com blocos pendentes e materiais por status', function () {
    $legislation = studyLegislation(30);
    studyMaterial($legislation, 0, 9, 'draft');
    studyMaterial($legislation, 10, 14, 'skipped');

    $this->withToken(agentToken())->getJson(STUDY_API.'/legislations')
        ->assertSuccessful()
        ->assertJsonPath('legislations.0.uuid', $legislation->uuid)
        ->assertJsonPath('legislations.0.total_blocks', 30)
        ->assertJsonPath('legislations.0.pending_blocks', 15)
        ->assertJsonPath('legislations.0.materials.draft', 1)
        ->assertJsonPath('legislations.0.materials.skipped', 1);
});

test('next avança depois de um material e depois de um trecho pulado', function () {
    $legislation = studyLegislation(60);
    $ids = studyBlockIds($legislation);
    $token = agentToken();

    $this->withToken($token)->getJson(STUDY_API.'/next?legislation='.$legislation->uuid)
        ->assertSuccessful()
        ->assertJsonPath('complete', false)
        ->assertJsonPath('first_block_id', $ids[0])
        ->assertJsonCount(30, 'blocks')
        ->assertJsonPath('blocks.0.text', 'Art. 0 A União zelará pelo dispositivo 0.')
        ->assertJsonPath('blocks.0.structural_marker', 'Art. 0')
        ->assertJsonPath('previous_material_title', null);

    $this->withToken($token)->postJson(STUDY_API, agentPayload($legislation, 0, 9))
        ->assertCreated()
        ->assertJsonPath('material.status', 'draft')
        ->assertJsonPath('pending_blocks', 50);

    $this->withToken($token)->getJson(STUDY_API.'/next?legislation='.$legislation->uuid)
        ->assertJsonPath('first_block_id', $ids[10])
        ->assertJsonPath('previous_material_title', 'Organização da União');

    $this->withToken($token)->postJson(STUDY_API.'/skip', [
        'legislation' => $legislation->uuid,
        'first_block_id' => $ids[10],
        'last_block_id' => $ids[49],
        'skip_reason' => 'Sequência de dispositivos revogados',
    ])->assertCreated()->assertJsonPath('material.status', 'skipped');

    $this->withToken($token)->getJson(STUDY_API.'/next?legislation='.$legislation->uuid)
        ->assertJsonPath('first_block_id', $ids[50])
        ->assertJsonCount(10, 'blocks');

    $material = StudyMaterial::where('status', 'draft')->firstOrFail();
    expect($material->questions)->toHaveCount(2)
        ->and($material->block_ids)->toBe(array_slice($ids, 0, 10))
        ->and($material->content_hash)->toHaveLength(64)
        ->and($material->generated_by_model)->toBe('claude-teste')
        ->and($material->questions[1]->options)->toHaveKeys(['A', 'B', 'C', 'D', 'E']);
});

test('next avisa quando a lei está completa', function () {
    $legislation = studyLegislation(8);
    studyMaterial($legislation, 0, 7, 'draft');

    $this->withToken(agentToken())->getJson(STUDY_API.'/next?legislation='.$legislation->uuid)
        ->assertSuccessful()
        ->assertJsonPath('complete', true)
        ->assertJsonCount(0, 'blocks');
});

test('o trecho não pode deixar buraco nem se sobrepor a outro', function () {
    $legislation = studyLegislation(40);
    $token = agentToken();

    // Buraco: começa depois do primeiro pendente
    $this->withToken($token)->postJson(STUDY_API, agentPayload($legislation, 2, 11))
        ->assertStatus(422)
        ->assertJsonValidationErrors('first_block_id');

    studyMaterial($legislation, 0, 9, 'draft');

    // Sobreposição no início: o bloco 5 já pertence ao primeiro trecho
    $this->withToken($token)->postJson(STUDY_API, agentPayload($legislation, 5, 14))
        ->assertStatus(422)
        ->assertJsonValidationErrors('first_block_id');

    // Sobreposição no fim: um trecho mais adiante limita onde este pode terminar
    studyMaterial($legislation, 20, 29, 'draft');
    $this->withToken($token)->postJson(STUDY_API, agentPayload($legislation, 10, 22))
        ->assertStatus(422)
        ->assertJsonValidationErrors('last_block_id');

    expect(StudyMaterial::count())->toBe(2);
});

test('o trecho tem de 4 a 20 blocos, salvo quando fecha a sequência pendente', function () {
    $legislation = studyLegislation(43);
    $token = agentToken();

    $this->withToken($token)->postJson(STUDY_API, agentPayload($legislation, 0, 2))
        ->assertStatus(422)->assertJsonValidationErrors('last_block_id');

    $this->withToken($token)->postJson(STUDY_API, agentPayload($legislation, 0, 20))
        ->assertStatus(422)->assertJsonValidationErrors('last_block_id');

    $this->withToken($token)->postJson(STUDY_API, agentPayload($legislation, 0, 19))->assertCreated();
    $this->withToken($token)->postJson(STUDY_API, agentPayload($legislation, 20, 39))->assertCreated();

    // Sobraram 3 blocos no fim da lei: 2 não fecham a sequência, 3 fecham
    $this->withToken($token)->postJson(STUDY_API, agentPayload($legislation, 40, 41))->assertStatus(422);
    $this->withToken($token)->postJson(STUDY_API, agentPayload($legislation, 40, 42))->assertCreated();
});

test('block_ids precisa ser um subconjunto não vazio do trecho', function () {
    $legislation = studyLegislation(30);
    $ids = studyBlockIds($legislation);
    $token = agentToken();

    $this->withToken($token)->postJson(STUDY_API, agentPayload($legislation, 0, 9, ['block_ids' => [$ids[0], $ids[15]]]))
        ->assertStatus(422)->assertJsonValidationErrors('block_ids');

    $this->withToken($token)->postJson(STUDY_API, agentPayload($legislation, 0, 9, ['block_ids' => []]))
        ->assertStatus(422)->assertJsonValidationErrors('block_ids');

    // Deixar blocos de fora (revogados) é permitido
    $this->withToken($token)->postJson(STUDY_API, agentPayload($legislation, 0, 9, ['block_ids' => [$ids[0], $ids[3], $ids[9]]]))
        ->assertCreated()
        ->assertJsonPath('material.covered_blocks', 3);
});

test('banca e tipo precisam ser coerentes', function (array $change, string $field) {
    $legislation = studyLegislation(20);
    $questions = studyQuestions();
    $questions[$change['index']] = array_merge($questions[$change['index']], $change['data']);

    $this->withToken(agentToken())->postJson(STUDY_API, agentPayload($legislation, 0, 9, ['questions' => $questions]))
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);

    expect(StudyMaterial::count())->toBe(0);
})->with([
    'cebraspe com alternativas' => [['index' => 0, 'data' => ['type' => 'multiple_choice']], 'questions.0.type'],
    'cebraspe com gabarito em letra' => [['index' => 0, 'data' => ['correct_answer' => 'A']], 'questions.0.correct_answer'],
    'fgv de certo/errado' => [['index' => 1, 'data' => ['type' => 'true_false']], 'questions.1.type'],
    'fgv com 4 alternativas' => [['index' => 1, 'data' => ['options' => ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd']]], 'questions.1.options'],
    'fgv com gabarito fora de A-E' => [['index' => 1, 'data' => ['correct_answer' => 'F']], 'questions.1.correct_answer'],
    'banca desconhecida' => [['index' => 1, 'data' => ['board' => 'esaf']], 'questions.1.board'],
    'sem comentário' => [['index' => 0, 'data' => ['explanation' => '']], 'questions.0.explanation'],
    'sem fundamento' => [['index' => 1, 'data' => ['legal_basis' => null]], 'questions.1.legal_basis'],
]);

test('o material aceita de 1 a 4 questões', function () {
    $legislation = studyLegislation(20);
    $token = agentToken();
    $five = array_fill(0, 5, studyQuestions()[0]);

    $this->withToken($token)->postJson(STUDY_API, agentPayload($legislation, 0, 9, ['questions' => []]))
        ->assertStatus(422)->assertJsonValidationErrors('questions');
    $this->withToken($token)->postJson(STUDY_API, agentPayload($legislation, 0, 9, ['questions' => $five]))
        ->assertStatus(422)->assertJsonValidationErrors('questions');
    $this->withToken($token)->postJson(STUDY_API, agentPayload($legislation, 0, 9, ['questions' => [studyQuestions()[0]]]))
        ->assertCreated();
});

test('trecho pulado aceita de 1 a 40 blocos e exige motivo', function () {
    $legislation = studyLegislation(60);
    $ids = studyBlockIds($legislation);
    $token = agentToken();

    $skip = fn (int $from, int $to, ?string $reason = 'Revogados') => $this->withToken($token)->postJson(STUDY_API.'/skip', [
        'legislation' => $legislation->uuid,
        'first_block_id' => $ids[$from],
        'last_block_id' => $ids[$to],
        'skip_reason' => $reason,
    ]);

    $skip(0, 0, null)->assertStatus(422)->assertJsonValidationErrors('skip_reason');
    $skip(1, 3)->assertStatus(422)->assertJsonValidationErrors('first_block_id');
    $skip(0, 40)->assertStatus(422)->assertJsonValidationErrors('last_block_id');
    $skip(0, 0)->assertCreated();
    $skip(1, 40)->assertCreated();
});

test('apagar material ou trecho pulado devolve os blocos a pendente', function () {
    $legislation = studyLegislation(30);
    $ids = studyBlockIds($legislation);
    $token = agentToken();

    $material = studyMaterial($legislation, 0, 9, 'draft');
    $skipped = studyMaterial($legislation, 10, 19, 'skipped');

    $this->withToken($token)->getJson(STUDY_API.'/next?legislation='.$legislation->uuid)
        ->assertJsonPath('first_block_id', $ids[20]);

    $skipped->delete();
    $this->withToken($token)->getJson(STUDY_API.'/next?legislation='.$legislation->uuid)
        ->assertJsonPath('first_block_id', $ids[10])
        ->assertJsonPath('pending_blocks', 20);

    $material->delete();
    $this->withToken($token)->getJson(STUDY_API.'/next?legislation='.$legislation->uuid)
        ->assertJsonPath('first_block_id', $ids[0])
        ->assertJsonPath('pending_blocks', 30);
});

test('status conta as questões por banca', function () {
    $legislation = studyLegislation(30);
    studyMaterial($legislation, 0, 9, 'draft');
    studyMaterial($legislation, 10, 19, 'published');

    $this->withToken(agentToken())->getJson(STUDY_API.'/status?legislation='.$legislation->uuid)
        ->assertSuccessful()
        ->assertJsonPath('pending_blocks', 10)
        ->assertJsonPath('complete', false)
        ->assertJsonPath('materials.draft', 1)
        ->assertJsonPath('materials.published', 1)
        ->assertJsonPath('questions_by_board.cebraspe', 2)
        ->assertJsonPath('questions_by_board.fgv', 2)
        ->assertJsonPath('questions_by_board.fcc', 0);
});

test('lei desconhecida devolve erro que o agente entende', function () {
    $this->withToken(agentToken())->getJson(STUDY_API.'/next?legislation=nao-existe')
        ->assertStatus(422)
        ->assertJsonValidationErrors('legislation');
});

test('bloco criado depois no meio de um trecho não vira pendente', function () {
    $legislation = studyLegislation(20);
    studyMaterial($legislation, 0, 9, 'draft');

    LegislationSegment::create([
        'legislation_id' => $legislation->id,
        'original_text' => 'Art. 4-A Novo.',
        'char_start' => 450,
        'char_end' => 470,
        'segment_type' => 'caput',
        'position' => 99,
        'is_block' => true,
    ]);

    $this->withToken(agentToken())->getJson(STUDY_API.'/next?legislation='.$legislation->uuid)
        ->assertJsonPath('pending_blocks', 10)
        ->assertJsonPath('first_block_id', studyBlockIds($legislation)[11]);
});
