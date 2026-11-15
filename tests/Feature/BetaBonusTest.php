<?php

use App\Http\Controllers\BetaBonusController;
use App\Models\LegislationSegment;
use App\Models\User;
use App\Models\UserSegmentProgress;
use App\Models\UserStudyMaterial;
use App\Models\XpTransaction;
use Inertia\Testing\AssertableInertia;

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

function bonusSubscriber(): User
{
    $user = studyAdmin(['lives' => 5]);
    giveActiveSubscription($user);

    return $user;
}

/** Os campos do mapa que decidem o avanço, sem o bônus. */
function mapProgress($test, User $user): array
{
    $sections = $test->actingAs($user)->get('/beta/play')->viewData('page')['props']['sections'];

    return array_map(fn ($section) => [
        'is_current' => $section['is_current'],
        'completed_phases' => $section['completed_phases'],
        'groups' => array_map(fn ($group) => array_diff_key($group, ['bonus' => true]), $section['groups']),
    ], $sections);
}

test('sem material publicado o quadrado não tem nó bônus', function () {
    $legislation = studyLegislation(85);
    studyMaterial($legislation, 0, 9, 'draft');
    studyMaterial($legislation, 10, 19, 'skipped');

    $this->actingAs(bonusSubscriber())->get('/beta/play')
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.groups.0.bonus', null)
            ->where('sections.0.groups.1.bonus', null)
            ->missing('sections.0.groups.0.block_ids'));
});

test('o material fica no quadrado em que cai o último bloco do trecho', function () {
    $legislation = studyLegislation(85);
    studyMaterial($legislation, 0, 9);
    studyMaterial($legislation, 10, 24);
    // Começa no primeiro quadrado e termina no segundo
    studyMaterial($legislation, 35, 44);

    $this->actingAs(bonusSubscriber())->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.groups.0.bonus.total', 2)
            ->where('sections.0.groups.0.bonus.premium_required', false)
            ->where('sections.0.groups.0.bonus.materials.0.title', 'Material 0-9')
            ->where('sections.0.groups.0.bonus.materials.1.title', 'Material 10-24')
            ->where('sections.0.groups.1.bonus.total', 1)
            ->where('sections.0.groups.1.bonus.materials.0.title', 'Material 35-44')
            ->where('sections.0.groups.2.bonus', null));
});

test('o material fica bloqueado enquanto faltam blocos cobertos e libera depois', function () {
    $legislation = studyLegislation(45);
    // Cobre só 3 dos 10 blocos do trecho
    $ids = studyBlockIds($legislation);
    $material = studyMaterial($legislation, 0, 9, 'published', [$ids[0], $ids[1], $ids[2]]);
    $user = bonusSubscriber();

    completeBlocks($user, $legislation, 0, 1);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.groups.0.bonus.unlocked', 0)
            ->where('sections.0.groups.0.bonus.completed', 0)
            ->where('sections.0.groups.0.bonus.materials.0.state', 'locked'));

    $this->actingAs($user)->get("/beta/play/bonus/{$material->uuid}")->assertRedirect(route('beta.play.map'));
    $this->actingAs($user)->postJson("/beta/play/bonus/{$material->uuid}/finish", ['answers' => [1 => 'certo', 2 => 'C']])->assertForbidden();

    // Não depende do quadrado inteiro: bastam os blocos cobertos
    completeBlocks($user, $legislation, 2, 2);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.groups.0.state', 'current')
            ->where('sections.0.groups.0.bonus.unlocked', 1)
            ->where('sections.0.groups.0.bonus.materials.0.state', 'available'));

    $this->actingAs($user)->get("/beta/play/bonus/{$material->uuid}")->assertSuccessful();
});

test('o avanço das fases é o mesmo com ou sem bônus', function () {
    $legislation = studyLegislation(85);
    $user = bonusSubscriber();
    completeBlocks($user, $legislation, 0, 14);

    $without = mapProgress($this, $user);

    studyMaterial($legislation, 0, 9);
    studyMaterial($legislation, 10, 19);
    studyMaterial($legislation, 40, 49);

    expect(mapProgress($this, $user))->toBe($without);

    // Concluir um material também não mexe no avanço
    $material = studyMaterial($legislation, 20, 23);
    completeBlocks($user, $legislation, 20, 23);
    $before = mapProgress($this, $user);

    $this->actingAs($user)->postJson("/beta/play/bonus/{$material->uuid}/finish", ['answers' => [1 => 'certo', 2 => 'C']])->assertSuccessful();

    expect(mapProgress($this, $user))->toBe($before);
});

test('a tela do material traz o resumo em HTML e as questões sem gabarito', function () {
    $legislation = studyLegislation(20);
    $material = studyMaterial($legislation, 0, 9);
    $material->update(['summary' => "## Tema\n\n**Forte** <script>alert(1)</script>"]);
    $user = bonusSubscriber();
    completeBlocks($user, $legislation, 0, 9);

    $this->actingAs($user)->get("/beta/play/bonus/{$material->uuid}")
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('BetaPlay/Bonus')
            ->where('material.title', 'Material 0-9')
            ->where('summaryHtml', fn ($html) => str_contains($html, '<h2>Tema</h2>') && ! str_contains($html, '<script'))
            ->has('questions', 2)
            ->where('questions.0.board', 'cebraspe')
            ->where('questions.1.options.C', 'A União')
            ->missing('questions.0.correct_answer')
            ->missing('questions.0.explanation')
            ->missing('questions.1.correct_answer')
            ->missing('questions.1.legal_basis')
            ->where('alreadyCompleted', false));
});

test('a resposta é corrigida no servidor, com comentário e fundamento', function () {
    $legislation = studyLegislation(20);
    $material = studyMaterial($legislation, 0, 9);
    $user = bonusSubscriber();
    completeBlocks($user, $legislation, 0, 9);

    $this->actingAs($user)->postJson("/beta/play/bonus/{$material->uuid}/answer", ['position' => 1, 'answer' => 'errado'])
        ->assertSuccessful()
        ->assertJsonPath('is_correct', false)
        ->assertJsonPath('correct_answer', 'certo')
        ->assertJsonPath('explanation', 'É o que diz o caput.')
        ->assertJsonPath('legal_basis', 'Art. 0');

    $this->actingAs($user)->postJson("/beta/play/bonus/{$material->uuid}/answer", ['position' => 2, 'answer' => 'c'])
        ->assertJsonPath('is_correct', true);

    // Responder não grava nada
    expect(UserStudyMaterial::count())->toBe(0);
});

test('concluir dá o XP uma única vez e conta na lei do material', function () {
    $legislation = studyLegislation(20);
    $material = studyMaterial($legislation, 0, 9);
    $user = bonusSubscriber();
    completeBlocks($user, $legislation, 0, 9);

    $expected = BetaBonusController::BONUS_XP_BASE + BetaBonusController::BONUS_XP_PER_CORRECT;

    // Um acerto (a segunda está errada), apesar de o navegador não mandar a contagem
    $this->actingAs($user)->postJson("/beta/play/bonus/{$material->uuid}/finish", ['answers' => [1 => 'certo', 2 => 'A']])
        ->assertSuccessful()
        ->assertJsonPath('correct_count', 1)
        ->assertJsonPath('total', 2)
        ->assertJsonPath('xp_gained', $expected)
        ->assertJsonPath('first_completion', true);

    $transaction = XpTransaction::where('user_id', $user->id)->where('source_type', 'study_material')->firstOrFail();
    expect($transaction->amount)->toBe($expected)
        ->and($transaction->source_id)->toBe($material->id)
        ->and($transaction->legislation_id)->toBe($legislation->id)
        ->and($user->fresh()->xp)->toBe($expected);

    // Refazer é permitido, sem XP
    $this->actingAs($user)->postJson("/beta/play/bonus/{$material->uuid}/finish", ['answers' => [1 => 'certo', 2 => 'C']])
        ->assertSuccessful()
        ->assertJsonPath('correct_count', 2)
        ->assertJsonPath('xp_gained', 0)
        ->assertJsonPath('first_completion', false);

    expect(XpTransaction::where('source_type', 'study_material')->count())->toBe(1)
        ->and($user->fresh()->xp)->toBe($expected)
        ->and($user->fresh()->lives)->toBe(5)
        ->and(UserStudyMaterial::firstOrFail()->correct_count)->toBe(2);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.groups.0.bonus.completed', 1)
            ->where('sections.0.groups.0.bonus.materials.0.state', 'completed'));
});

test('concluir exige todas as questões respondidas', function () {
    $legislation = studyLegislation(20);
    $material = studyMaterial($legislation, 0, 9);
    $user = bonusSubscriber();
    completeBlocks($user, $legislation, 0, 9);

    $this->actingAs($user)->postJson("/beta/play/bonus/{$material->uuid}/finish", ['answers' => [1 => 'certo']])->assertStatus(422);

    expect(UserStudyMaterial::count())->toBe(0)->and($user->fresh()->xp)->toBe(0);
});

test('não assinante vê o nó mas não abre nem conclui o material', function () {
    $legislation = studyLegislation(45);
    $material = studyMaterial($legislation, 0, 9);
    $user = studyAdmin();
    completeBlocks($user, $legislation, 0, 9);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.groups.0.bonus.total', 1)
            ->where('sections.0.groups.0.bonus.premium_required', true)
            ->where('sections.0.groups.0.bonus.materials', []));

    $this->actingAs($user)->get("/beta/play/bonus/{$material->uuid}")->assertRedirect(route('subscription.index'));
    $this->actingAs($user)->postJson("/beta/play/bonus/{$material->uuid}/answer", ['position' => 1, 'answer' => 'certo'])->assertForbidden();
    $this->actingAs($user)->postJson("/beta/play/bonus/{$material->uuid}/finish", ['answers' => [1 => 'certo', 2 => 'C']])->assertForbidden();

    expect(UserStudyMaterial::count())->toBe(0)->and($user->fresh()->xp)->toBe(0);
});

test('rascunho e material desatualizado não aparecem no mapa nem abrem', function () {
    $legislation = studyLegislation(45);
    $draft = studyMaterial($legislation, 0, 9, 'draft');
    $stale = studyMaterial($legislation, 10, 19);
    $user = bonusSubscriber();
    completeBlocks($user, $legislation, 0, 19);

    LegislationSegment::find($stale->block_ids[0])->update(['original_text' => 'Art. 10 Redação nova.']);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('sections.0.groups.0.bonus', null));

    $this->actingAs($user)->get("/beta/play/bonus/{$draft->uuid}")->assertNotFound();
    $this->actingAs($user)->get("/beta/play/bonus/{$stale->uuid}")->assertNotFound();
    $this->actingAs($user)->postJson("/beta/play/bonus/{$stale->uuid}/finish", ['answers' => [1 => 'certo', 2 => 'C']])->assertNotFound();
});

test('o mapa com bônus não faz uma consulta por material', function () {
    $legislation = studyLegislation(160);
    $user = bonusSubscriber();

    $count = function () use ($user) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get('/beta/play')->assertSuccessful();

        return count(DB::getQueryLog());
    };

    studyMaterial($legislation, 0, 9);
    $count(); // a primeira requisição faz consultas a mais (sessão, assinatura)
    $one = $count();

    foreach ([[10, 19], [20, 29], [30, 39], [40, 49], [50, 59], [60, 69], [70, 79]] as [$from, $to]) {
        studyMaterial($legislation, $from, $to);
    }

    expect($count())->toBe($one);
});
