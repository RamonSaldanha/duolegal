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

// Uma etapa tem 4 quadrados de 4 fases de 10 blocos: 160 blocos. Com 170 blocos a lei fica
// com duas etapas: a primeira cheia (blocos 0 a 159) e a segunda com uma fase (160 a 169).

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

function checkpointSubscriber(): User
{
    $user = studyAdmin(['lives' => 5]);
    giveActiveSubscription($user);

    return $user;
}

/** Uuid do último bloco da etapa: é a chave do desafio dela. */
function checkpointKey($legislation, int $lastBlock): string
{
    return LegislationSegment::findOrFail(studyBlockIds($legislation)[$lastBlock])->uuid;
}

/** Os campos do mapa que decidem o avanço, sem o desafio da etapa. */
function mapProgress($test, User $user): array
{
    $sections = $test->actingAs($user)->get('/beta/play')->viewData('page')['props']['sections'];

    return array_map(fn ($section) => array_diff_key($section, ['checkpoint' => true]), $sections);
}

test('toda etapa tem o seu desafio, mesmo sem material', function () {
    $legislation = studyLegislation(170);
    $user = checkpointSubscriber();

    $this->actingAs($user)->get('/beta/play')
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('sections', 2)
            ->where('sections.0.checkpoint.state', 'locked')
            ->where('sections.0.checkpoint.total', 0)
            ->where('sections.0.checkpoint.key', checkpointKey($legislation, 159))
            ->where('sections.1.checkpoint.state', 'locked')
            ->where('sections.1.checkpoint.key', checkpointKey($legislation, 169))
            ->missing('sections.0.checkpoint.material_ids')
            ->missing('sections.0.checkpoint.anchor_block_id')
            ->missing('sections.0.groups.0.block_ids'));

    // Etapa concluída sem nenhum material: aparece, mas sem conteúdo
    completeBlocks($user, $legislation, 0, 159);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('sections.0.checkpoint.state', 'empty'));

    $this->actingAs($user)->get('/beta/play/etapa/'.checkpointKey($legislation, 159))->assertRedirect(route('beta.play.map'));
});

test('o desafio tem só os materiais da própria etapa', function () {
    $legislation = studyLegislation(170);
    studyMaterial($legislation, 0, 9);
    // Começa na primeira etapa e termina na segunda: fica na segunda
    studyMaterial($legislation, 150, 165);
    studyMaterial($legislation, 20, 29, 'draft');

    $this->actingAs(checkpointSubscriber())->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.checkpoint.total', 1)
            ->where('sections.0.checkpoint.questions', 2)
            // O material da primeira etapa não se repete na segunda
            ->where('sections.1.checkpoint.total', 1)
            ->where('sections.1.checkpoint.questions', 2));
});

test('só libera com a etapa inteira concluída', function () {
    $legislation = studyLegislation(170);
    studyMaterial($legislation, 0, 9);
    $user = checkpointSubscriber();
    $key = checkpointKey($legislation, 159);

    // Os blocos do material já foram feitos, mas falta o resto da etapa
    completeBlocks($user, $legislation, 0, 158);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('sections.0.checkpoint.state', 'locked'));
    $this->actingAs($user)->get("/beta/play/etapa/{$key}")->assertRedirect(route('beta.play.map'));

    completeBlocks($user, $legislation, 159, 159);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.checkpoint.state', 'available')
            ->where('sections.0.checkpoint.completed', 0));
    $this->actingAs($user)->get("/beta/play/etapa/{$key}")->assertSuccessful();
});

test('a tela traz os resumos em HTML e as questões sem gabarito, na ordem da trilha', function () {
    $legislation = studyLegislation(170);
    $second = studyMaterial($legislation, 30, 39);
    $first = studyMaterial($legislation, 0, 9);
    $first->update(['summary' => "## Tema\n\n**Forte** <script>alert(1)</script>"]);
    $user = checkpointSubscriber();
    completeBlocks($user, $legislation, 0, 159);

    $this->actingAs($user)->get('/beta/play/etapa/'.checkpointKey($legislation, 159))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('BetaPlay/Checkpoint')
            ->where('checkpoint.number', 1)
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

/** Responde uma questão do desafio. */
function answerCheckpoint($test, User $user, string $key, $material, int $position, string $answer)
{
    return $test->actingAs($user)->postJson("/beta/play/etapa/{$key}/answer", ['material' => $material->uuid, 'position' => $position, 'answer' => $answer]);
}

test('a resposta é corrigida no servidor e só vale para material da etapa', function () {
    $legislation = studyLegislation(170);
    $material = studyMaterial($legislation, 0, 9);
    $later = studyMaterial($legislation, 160, 169);
    $user = checkpointSubscriber();
    completeBlocks($user, $legislation, 0, 169);
    $key = checkpointKey($legislation, 159);

    answerCheckpoint($this, $user, $key, $material, 1, 'errado')
        ->assertSuccessful()
        ->assertJsonPath('is_correct', false)
        ->assertJsonPath('correct_answer', 'certo')
        ->assertJsonPath('explanation', 'É o que diz o caput.')
        ->assertJsonPath('legal_basis', 'Art. 0')
        ->assertJsonPath('material_completed', false);

    // Material de uma etapa posterior não entra no desafio desta
    answerCheckpoint($this, $user, $key, $later, 1, 'certo')->assertNotFound();
    $this->actingAs($user)->postJson("/beta/play/etapa/{$key}/summary", ['material' => $later->uuid])->assertNotFound();

    // Nem o de uma etapa anterior entra no desafio da seguinte
    $next = checkpointKey($legislation, 169);
    answerCheckpoint($this, $user, $next, $material, 2, 'C')->assertNotFound();
    $this->actingAs($user)->postJson("/beta/play/etapa/{$next}/summary", ['material' => $material->uuid])->assertNotFound();

    answerCheckpoint($this, $user, $key, $material, 9, 'certo')->assertStatus(422);

    expect(UserStudyMaterial::count())->toBe(0)
        ->and(UserBetaCheckpoint::where('user_id', $user->id)->sole()->in_progress)->toBe([$material->id => ['summary' => false, 'answers' => [1 => 'errado']]]);
});

test('cada passo fica gravado e a pessoa volta de onde parou', function () {
    $legislation = studyLegislation(170);
    $first = studyMaterial($legislation, 0, 9);
    $second = studyMaterial($legislation, 10, 19);
    $user = checkpointSubscriber();
    completeBlocks($user, $legislation, 0, 159);
    $key = checkpointKey($legislation, 159);

    $this->actingAs($user)->postJson("/beta/play/etapa/{$key}/summary", ['material' => $first->uuid])
        ->assertSuccessful()
        ->assertJsonPath('material_completed', false)
        ->assertJsonPath('xp_gained', 0);
    answerCheckpoint($this, $user, $key, $first, 1, 'certo')->assertJsonPath('is_correct', true);

    // Saiu e voltou: o resumo lido e a questão respondida continuam lá, sem XP ainda
    $this->actingAs($user)->get("/beta/play/etapa/{$key}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('materials.0.done', false)
            ->where('materials.0.summary_read', true)
            ->where('materials.0.answered', [1])
            ->where('materials.1.summary_read', false)
            ->where('materials.1.answered', []));
    expect($user->fresh()->xp)->toBe(0);

    // Vale a primeira resposta: responder de novo não troca o que foi gravado
    answerCheckpoint($this, $user, $key, $first, 1, 'errado')->assertJsonPath('is_correct', true);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('sections.0.checkpoint.completed', 0));

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

    $this->actingAs($user)->get("/beta/play/etapa/{$key}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('checkpoint.completed', 1)
            ->where('materials.0.done', true)
            ->where('materials.0.answered', [])
            ->where('materials.1.done', false));
});

test('concluir conta o material na etapa e dá o XP uma única vez', function () {
    $legislation = studyLegislation(170);
    $material = studyMaterial($legislation, 0, 9);
    $other = studyMaterial($legislation, 10, 19);
    $user = checkpointSubscriber();
    completeBlocks($user, $legislation, 0, 169);
    $first = checkpointKey($legislation, 159);

    $expected = BetaCheckpointController::MATERIAL_XP_BASE + BetaCheckpointController::MATERIAL_XP_PER_CORRECT;

    // Um acerto (a segunda está errada)
    answerCheckpoint($this, $user, $first, $material, 1, 'certo')->assertJsonPath('material_completed', false);
    answerCheckpoint($this, $user, $first, $material, 2, 'A')
        ->assertSuccessful()
        ->assertJsonPath('is_correct', false)
        ->assertJsonPath('material_completed', true)
        ->assertJsonPath('xp_gained', $expected)
        ->assertJsonPath('checkpoint.completed', 1)
        ->assertJsonPath('checkpoint.total', 2);

    $transaction = XpTransaction::where('user_id', $user->id)->where('source_type', 'study_material')->firstOrFail();
    expect($transaction->amount)->toBe($expected)
        ->and($transaction->source_id)->toBe($material->id)
        ->and($transaction->legislation_id)->toBe($legislation->id)
        ->and(UserStudyMaterial::where('study_material_id', $material->id)->firstOrFail()->correct_count)->toBe(1);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.checkpoint.state', 'available')
            ->where('sections.0.checkpoint.completed', 1)
            // A etapa seguinte não tem material: nada do que foi feito aqui aparece lá
            ->where('sections.1.checkpoint.total', 0)
            ->where('sections.1.checkpoint.completed', 0));

    answerCheckpoint($this, $user, $first, $other, 1, 'certo');
    answerCheckpoint($this, $user, $first, $other, 2, 'C')->assertJsonPath('checkpoint.completed', 2);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('sections.0.checkpoint.state', 'complete'));

    // Refazer o mesmo material (revisão) só corrige: sem XP e sem gravar nada
    $this->actingAs($user)->postJson("/beta/play/etapa/{$first}/summary", ['material' => $material->uuid])->assertSuccessful();
    answerCheckpoint($this, $user, $first, $material, 1, 'certo');
    answerCheckpoint($this, $user, $first, $material, 2, 'C')
        ->assertSuccessful()
        ->assertJsonPath('is_correct', true)
        ->assertJsonPath('material_completed', false)
        ->assertJsonPath('xp_gained', 0)
        ->assertJsonPath('checkpoint.completed', 2);

    expect(XpTransaction::where('source_type', 'study_material')->count())->toBe(2)
        ->and($user->fresh()->lives)->toBe(5)
        ->and(UserBetaCheckpoint::where('user_id', $user->id)->sole()->in_progress)->toBeNull()
        ->and(UserStudyMaterial::where('study_material_id', $material->id)->firstOrFail()->correct_count)->toBe(1);

    // Material novo publicado depois: a etapa concluída volta a ter o que fazer
    studyMaterial($legislation, 40, 49);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.checkpoint.state', 'available')
            ->where('sections.0.checkpoint.completed', 2)
            ->where('sections.0.checkpoint.total', 3));
});

test('não assinante vê o desafio mas não abre nem conclui', function () {
    $legislation = studyLegislation(170);
    $material = studyMaterial($legislation, 0, 9);
    $user = studyAdmin();
    completeBlocks($user, $legislation, 0, 159);
    $key = checkpointKey($legislation, 159);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.checkpoint.state', 'available')
            ->where('sections.0.checkpoint.premium_required', true));

    $this->actingAs($user)->get("/beta/play/etapa/{$key}")->assertRedirect(route('beta.play.map'));
    answerCheckpoint($this, $user, $key, $material, 1, 'certo')->assertForbidden();
    $this->actingAs($user)->postJson("/beta/play/etapa/{$key}/summary", ['material' => $material->uuid])->assertForbidden();

    expect(UserBetaCheckpoint::count())->toBe(0)->and($user->fresh()->xp)->toBe(0);
});

test('rascunho e material desatualizado ficam de fora', function () {
    $legislation = studyLegislation(170);
    $draft = studyMaterial($legislation, 0, 9, 'draft');
    $stale = studyMaterial($legislation, 10, 19);
    $user = checkpointSubscriber();
    completeBlocks($user, $legislation, 0, 159);
    $key = checkpointKey($legislation, 159);

    LegislationSegment::find($stale->block_ids[0])->update(['original_text' => 'Art. 10 Redação nova.']);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('sections.0.checkpoint.state', 'empty'));

    answerCheckpoint($this, $user, $key, $draft, 1, 'certo')->assertForbidden();
});

test('o avanço das fases é o mesmo com ou sem desafio', function () {
    $legislation = studyLegislation(170);
    $user = checkpointSubscriber();
    completeBlocks($user, $legislation, 0, 14);

    $without = mapProgress($this, $user);

    studyMaterial($legislation, 0, 9);
    studyMaterial($legislation, 10, 19);
    studyMaterial($legislation, 160, 169);

    expect(mapProgress($this, $user))->toBe($without);
});

test('o mapa não faz uma consulta por material', function () {
    $legislation = studyLegislation(170);
    $user = checkpointSubscriber();

    $count = function () use ($user) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get('/beta/play')->assertSuccessful();

        return count(DB::getQueryLog());
    };

    studyMaterial($legislation, 0, 9);
    $count(); // a primeira requisição faz consultas a mais (sessão, assinatura)
    $one = $count();

    foreach ([[10, 19], [20, 29], [30, 39], [40, 49], [50, 59], [160, 169]] as [$from, $to]) {
        studyMaterial($legislation, $from, $to);
    }

    expect($count())->toBe($one);
});
