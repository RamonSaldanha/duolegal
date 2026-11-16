<?php

use App\Http\Controllers\BetaCheckpointController;
use App\Models\LegislationSegment;
use App\Models\User;
use App\Models\UserBetaCheckpoint;
use App\Models\UserSegmentProgress;
use App\Models\UserStudyMaterial;
use App\Models\XpTransaction;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

// Um quadrado tem 4 fases de 10 blocos (40 blocos) e uma etapa tem 4 quadrados (160 blocos).
// Com 170 blocos a lei fica com duas etapas: a primeira cheia (quadrados 0-39, 40-79, 80-119
// e 120-159) e a segunda com um quadrado de uma fase só (160-169). O material de um quadrado
// é o que termina dentro dele.

/** Marca como concluídos os blocos de $from a $to (posições na lei). */
function completeBlocks(User $user, $legislation, int $from, int $to): void
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

/** Quem joga o mapa beta nos testes: admin (o mapa é só para eles), sem assinatura. */
function betaPlayer(): User
{
    return studyAdmin(['lives' => 5]);
}

/** Uuid do bloco nessa posição: o último bloco do quadrado é a chave do desafio dele. */
function checkpointKey($legislation, int $lastBlock): string
{
    return LegislationSegment::findOrFail(studyBlockIds($legislation)[$lastBlock])->uuid;
}

/** Responde uma questão do desafio. */
function answerCheckpoint($test, User $user, string $key, $material, int $position, string $answer)
{
    return $test->actingAs($user)->postJson("/beta/play/desafio/{$key}/answer", ['material' => $material->uuid, 'position' => $position, 'answer' => $answer]);
}

test('o quadrado com material ganha o desafio como último passo', function () {
    $legislation = studyLegislation(170);
    studyMaterial($legislation, 0, 39);
    // Começa num quadrado e termina no outro: fica no quadrado em que termina
    studyMaterial($legislation, 70, 85);
    studyMaterial($legislation, 120, 159, 'draft');

    $this->actingAs(betaPlayer())->get('/beta/play')
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('sections', 2)
            ->has('sections.0.groups', 4)
            ->where('sections.0.groups.0.total_steps', 5)
            ->where('sections.0.groups.0.completed_steps', 0)
            ->where('sections.0.groups.0.challenge.state', 'locked')
            ->where('sections.0.groups.0.challenge.key', checkpointKey($legislation, 39))
            ->where('sections.0.groups.0.challenge.total', 1)
            ->where('sections.0.groups.0.challenge.questions', 2)
            // Quadrado sem material: só as 4 fases
            ->where('sections.0.groups.1.total_steps', 4)
            ->where('sections.0.groups.1.challenge.state', 'none')
            ->where('sections.0.groups.2.challenge.total', 1)
            // Rascunho não conta
            ->where('sections.0.groups.3.challenge.state', 'none')
            ->where('sections.0.total_steps', 18)
            ->missing('sections.0.groups.0.challenge.material_ids')
            ->missing('sections.0.groups.0.challenge.anchor_block_id')
            ->missing('sections.0.groups.0.block_ids'));
});

test('com as fases feitas o desafio é o passo atual e segura o quadrado seguinte', function () {
    $legislation = studyLegislation(170);
    $material = studyMaterial($legislation, 0, 39);
    $user = betaPlayer();
    $key = checkpointKey($legislation, 39);

    // Falta uma fase do quadrado: o desafio ainda não abre
    completeBlocks($user, $legislation, 0, 29);
    $this->actingAs($user)->get("/beta/play/desafio/{$key}")->assertRedirect(route('beta.play.map'));
    answerCheckpoint($this, $user, $key, $material, 1, 'certo')->assertForbidden();

    completeBlocks($user, $legislation, 30, 39);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.groups.0.state', 'current')
            ->where('sections.0.groups.0.current_phase_id', null)
            ->where('sections.0.groups.0.completed_steps', 4)
            ->where('sections.0.groups.0.challenge.state', 'available')
            ->where('sections.0.groups.0.challenge.is_current', true)
            ->where('sections.0.groups.1.state', 'locked')
            ->where('sections.0.groups.1.current_phase_id', null));

    // A primeira fase do quadrado seguinte (fase 5) fica fechada até o desafio
    $this->actingAs($user)->get('/beta/play/phase/5')->assertRedirect(route('beta.play.map'));
    $this->actingAs($user)->get("/beta/play/desafio/{$key}")->assertSuccessful();

    answerCheckpoint($this, $user, $key, $material, 1, 'certo');
    answerCheckpoint($this, $user, $key, $material, 2, 'C')->assertJsonPath('material_completed', true);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.groups.0.state', 'complete')
            ->where('sections.0.groups.0.completed_steps', 5)
            ->where('sections.0.groups.0.challenge.state', 'complete')
            ->where('sections.0.groups.0.challenge.is_current', false)
            ->where('sections.0.groups.1.state', 'current')
            ->where('sections.0.groups.1.current_phase_id', 5));

    $this->actingAs($user)->get('/beta/play/phase/5')
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('phaseNumber', 1)
            ->where('phasesPerGroup', 4));
});

test('quadrado sem material não segura o avanço', function () {
    $legislation = studyLegislation(170);
    $user = betaPlayer();
    completeBlocks($user, $legislation, 0, 39);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.groups.0.state', 'complete')
            ->where('sections.0.groups.0.completed_steps', 4)
            ->where('sections.0.groups.1.state', 'current')
            ->where('sections.0.groups.1.current_phase_id', 5));
});

test('material publicado depois não puxa de volta quem já passou do quadrado', function () {
    $legislation = studyLegislation(170);
    $user = betaPlayer();
    // Já fez a primeira fase do segundo quadrado
    completeBlocks($user, $legislation, 0, 49);

    studyMaterial($legislation, 0, 39);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.groups.0.state', 'complete')
            ->where('sections.0.groups.0.completed_steps', 4)
            ->where('sections.0.groups.0.total_steps', 5)
            ->where('sections.0.groups.0.challenge.state', 'available')
            ->where('sections.0.groups.0.challenge.is_current', false)
            ->where('sections.0.groups.1.state', 'current')
            ->where('sections.0.groups.1.current_phase_id', 6));

    $this->actingAs($user)->get('/beta/play/phase/6')->assertSuccessful();
    $this->actingAs($user)->get('/beta/play/desafio/'.checkpointKey($legislation, 39))->assertSuccessful();
});

test('o desafio do último quadrado da trilha também é o passo atual', function () {
    $legislation = studyLegislation(40);
    studyMaterial($legislation, 0, 39);
    $user = betaPlayer();
    completeBlocks($user, $legislation, 0, 39);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.groups.0.state', 'current')
            ->where('sections.0.groups.0.challenge.is_current', true));
});

test('a tela traz o resumo em HTML e as questões sem gabarito', function () {
    $legislation = studyLegislation(170);
    // Dois materiais terminando no mesmo quadrado (gravados antes da regra de um por quadrado)
    $second = studyMaterial($legislation, 30, 39);
    $first = studyMaterial($legislation, 0, 9);
    $first->update(['summary' => "## Tema\n\n**Forte** <script>alert(1)</script>"]);
    $user = betaPlayer();
    completeBlocks($user, $legislation, 0, 39);

    $this->actingAs($user)->get('/beta/play/desafio/'.checkpointKey($legislation, 39))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('BetaPlay/Checkpoint')
            ->where('checkpoint.section_number', 1)
            ->where('checkpoint.step_number', 5)
            ->where('checkpoint.total', 2)
            ->has('materials', 2)
            ->where('materials.0.uuid', $first->uuid)
            ->where('materials.1.uuid', $second->uuid)
            ->where('materials.0.legislation_title', 'Lei de Estudo')
            ->where('materials.0.summary_html', fn ($html) => str_contains($html, '<h2>Tema</h2>') && ! str_contains($html, '<script'))
            ->where('materials.0.done', false)
            ->where('materials.0.summary_read', false)
            ->where('materials.0.answered', [])
            ->has('materials.0.questions', 2)
            ->where('materials.0.questions.1.options.C', 'A União')
            ->missing('materials.0.questions.0.correct_answer')
            ->missing('materials.0.questions.0.explanation')
            ->missing('materials.0.questions.1.legal_basis'));
});

test('a resposta é corrigida no servidor e só vale para material do quadrado', function () {
    $legislation = studyLegislation(170);
    $material = studyMaterial($legislation, 0, 39);
    $later = studyMaterial($legislation, 40, 79);
    $user = betaPlayer();
    completeBlocks($user, $legislation, 0, 79);
    $key = checkpointKey($legislation, 39);

    answerCheckpoint($this, $user, $key, $material, 1, 'errado')
        ->assertSuccessful()
        ->assertJsonPath('is_correct', false)
        ->assertJsonPath('correct_answer', 'certo')
        ->assertJsonPath('explanation', 'É o que diz o caput.')
        ->assertJsonPath('legal_basis', 'Art. 0')
        ->assertJsonPath('material_completed', false);

    // Material de outro quadrado não entra no desafio deste
    answerCheckpoint($this, $user, $key, $later, 1, 'certo')->assertNotFound();
    $this->actingAs($user)->postJson("/beta/play/desafio/{$key}/summary", ['material' => $later->uuid])->assertNotFound();

    $next = checkpointKey($legislation, 79);
    answerCheckpoint($this, $user, $next, $material, 2, 'C')->assertNotFound();
    $this->actingAs($user)->postJson("/beta/play/desafio/{$next}/summary", ['material' => $material->uuid])->assertNotFound();

    answerCheckpoint($this, $user, $key, $material, 9, 'certo')->assertStatus(422);

    // Chave que não é o último bloco de um quadrado
    $this->actingAs($user)->get('/beta/play/desafio/'.checkpointKey($legislation, 20))->assertNotFound();

    expect(UserStudyMaterial::count())->toBe(0)
        ->and(UserBetaCheckpoint::where('user_id', $user->id)->sole()->in_progress)->toBe([$material->id => ['summary' => false, 'answers' => [1 => 'errado']]]);
});

test('cada passo fica gravado e a pessoa volta de onde parou', function () {
    $legislation = studyLegislation(170);
    $first = studyMaterial($legislation, 0, 9);
    $second = studyMaterial($legislation, 10, 39);
    $user = betaPlayer();
    completeBlocks($user, $legislation, 0, 39);
    $key = checkpointKey($legislation, 39);

    $this->actingAs($user)->postJson("/beta/play/desafio/{$key}/summary", ['material' => $first->uuid])
        ->assertSuccessful()
        ->assertJsonPath('material_completed', false)
        ->assertJsonPath('xp_gained', 0);
    answerCheckpoint($this, $user, $key, $first, 1, 'certo')->assertJsonPath('is_correct', true);

    // Saiu e voltou: o resumo lido e a questão respondida continuam lá, sem XP ainda
    $this->actingAs($user)->get("/beta/play/desafio/{$key}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('materials.0.done', false)
            ->where('materials.0.summary_read', true)
            ->where('materials.0.answered', [1])
            ->where('materials.1.summary_read', false)
            ->where('materials.1.answered', []));
    expect($user->fresh()->xp)->toBe(0);

    // Vale a primeira resposta: responder de novo não troca o que foi gravado
    answerCheckpoint($this, $user, $key, $first, 1, 'errado')->assertJsonPath('is_correct', true);

    $base = BetaCheckpointController::MATERIAL_XP_BASE;
    $perCorrect = BetaCheckpointController::MATERIAL_XP_PER_CORRECT;

    // A última questão conclui o material e dá o XP na hora
    answerCheckpoint($this, $user, $key, $first, 2, 'c')
        ->assertJsonPath('is_correct', true)
        ->assertJsonPath('material_completed', true)
        ->assertJsonPath('xp_gained', $base + 2 * $perCorrect)
        ->assertJsonPath('checkpoint.completed', 1)
        ->assertJsonPath('checkpoint.total', 2)
        ->assertJsonPath('user.xp', $base + 2 * $perCorrect);

    $progress = UserBetaCheckpoint::where('user_id', $user->id)->sole();
    expect($progress->doneIds())->toBe([$first->id])->and($progress->in_progress)->toBeNull();

    // Falta o outro material do quadrado: o desafio continua por fazer
    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.groups.0.challenge.state', 'available')
            ->where('sections.0.groups.0.challenge.completed', 1));

    $this->actingAs($user)->get("/beta/play/desafio/{$key}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('checkpoint.completed', 1)
            ->where('materials.0.done', true)
            ->where('materials.0.answered', [])
            ->where('materials.1.done', false));
});

test('concluir dá o XP uma única vez, sem assinatura e sem gastar vida', function () {
    $legislation = studyLegislation(170);
    $material = studyMaterial($legislation, 0, 39);
    $user = betaPlayer();
    completeBlocks($user, $legislation, 0, 39);
    $key = checkpointKey($legislation, 39);

    $expected = BetaCheckpointController::MATERIAL_XP_BASE + BetaCheckpointController::MATERIAL_XP_PER_CORRECT;

    // Um acerto (a segunda está errada)
    answerCheckpoint($this, $user, $key, $material, 1, 'certo')->assertJsonPath('material_completed', false);
    answerCheckpoint($this, $user, $key, $material, 2, 'A')
        ->assertSuccessful()
        ->assertJsonPath('is_correct', false)
        ->assertJsonPath('material_completed', true)
        ->assertJsonPath('xp_gained', $expected)
        ->assertJsonPath('checkpoint.completed', 1)
        ->assertJsonPath('checkpoint.total', 1);

    $transaction = XpTransaction::where('user_id', $user->id)->where('source_type', 'study_material')->firstOrFail();
    expect($transaction->amount)->toBe($expected)
        ->and($transaction->source_id)->toBe($material->id)
        ->and($transaction->legislation_id)->toBe($legislation->id)
        ->and(UserStudyMaterial::where('study_material_id', $material->id)->firstOrFail()->correct_count)->toBe(1);

    // Refazer o mesmo material (revisão) só corrige: sem XP e sem gravar nada
    $this->actingAs($user)->postJson("/beta/play/desafio/{$key}/summary", ['material' => $material->uuid])->assertSuccessful();
    answerCheckpoint($this, $user, $key, $material, 1, 'certo');
    answerCheckpoint($this, $user, $key, $material, 2, 'C')
        ->assertSuccessful()
        ->assertJsonPath('is_correct', true)
        ->assertJsonPath('material_completed', false)
        ->assertJsonPath('xp_gained', 0)
        ->assertJsonPath('checkpoint.completed', 1);

    expect(XpTransaction::where('source_type', 'study_material')->count())->toBe(1)
        ->and($user->fresh()->lives)->toBe(5)
        ->and(UserBetaCheckpoint::where('user_id', $user->id)->sole()->in_progress)->toBeNull()
        ->and(UserStudyMaterial::where('study_material_id', $material->id)->firstOrFail()->correct_count)->toBe(1);
});

test('rascunho e material desatualizado ficam de fora', function () {
    $legislation = studyLegislation(170);
    $draft = studyMaterial($legislation, 0, 9, 'draft');
    $stale = studyMaterial($legislation, 10, 39);
    $user = betaPlayer();
    completeBlocks($user, $legislation, 0, 39);
    $key = checkpointKey($legislation, 39);

    LegislationSegment::find($stale->block_ids[0])->update(['original_text' => 'Art. 10 Redação nova.']);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.groups.0.challenge.state', 'none')
            ->where('sections.0.groups.0.total_steps', 4)
            ->where('sections.0.groups.1.state', 'current'));

    answerCheckpoint($this, $user, $key, $draft, 1, 'certo')->assertForbidden();
});

test('o mapa não faz uma consulta por material', function () {
    $legislation = studyLegislation(170);
    $user = betaPlayer();

    $count = function () use ($user) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get('/beta/play')->assertSuccessful();

        return count(DB::getQueryLog());
    };

    studyMaterial($legislation, 0, 9);
    $count(); // a primeira requisição faz consultas a mais (sessão, assinatura)
    $one = $count();

    foreach ([[10, 39], [40, 79], [80, 119], [120, 159], [160, 169]] as [$from, $to]) {
        studyMaterial($legislation, $from, $to);
    }

    expect($count())->toBe($one);
});
