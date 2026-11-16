<?php

use App\Http\Middleware\StudyMaterialAgent;
use App\Models\LegislationSegment;
use App\Models\StudyMaterial;
use App\Models\User;
use App\Services\StudyMaterialService;
use Inertia\Testing\AssertableInertia;

function adminEditPayload(StudyMaterial $material, array $overrides = []): array
{
    return array_merge([
        'title' => 'Título revisado',
        'summary' => "## Novo\n\nTexto **revisado**.",
        'block_ids' => $material->block_ids,
        'questions' => studyQuestions(),
    ], $overrides);
}

test('as páginas de material de estudo são só para admin', function () {
    $legislation = studyLegislation(10);
    $material = studyMaterial($legislation, 0, 9, 'draft');
    $user = User::factory()->createOne();

    $this->actingAs($user)->get('/admin/study-materials')->assertRedirect(route('play.map'));
    $this->actingAs($user)->get("/admin/study-materials/{$material->uuid}")->assertRedirect(route('play.map'));
    $this->actingAs($user)->get('/admin/study-materials/instalar')->assertRedirect(route('play.map'));
    $this->actingAs($user)->post('/admin/study-materials/tokens')->assertRedirect(route('play.map'));
    $this->actingAs($user)->delete("/admin/study-materials/{$material->uuid}")->assertRedirect(route('play.map'));

    expect(StudyMaterial::count())->toBe(1)->and($user->tokens()->count())->toBe(0);
});

test('a lista mostra os materiais da lei com intervalo, situação e progresso', function () {
    $legislation = studyLegislation(60);
    studyMaterial($legislation, 0, 9, 'published');
    studyMaterial($legislation, 10, 19, 'draft');
    studyMaterial($legislation, 20, 44, 'skipped');

    $this->actingAs(studyAdmin())->get('/admin/study-materials?legislation='.$legislation->uuid)
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/StudyMaterials/Index')
            ->where('selected.uuid', $legislation->uuid)
            ->where('progress.total_blocks', 60)
            ->where('progress.pending_blocks', 15)
            ->has('materials', 3)
            ->where('materials.0.status', 'published')
            ->where('materials.0.first_label', 'Art. 0')
            ->where('materials.0.last_label', 'Art. 9')
            ->where('materials.0.range_blocks', 10)
            ->where('materials.0.square', 1)
            ->where('materials.0.is_stale', false)
            ->where('materials.1.status', 'draft')
            ->where('materials.2.status', 'skipped')
            ->where('materials.2.skip_reason', 'Disposições transitórias esgotadas')
            ->where('materials.2.square', 2));
});

test('o admin salva resumo e questões, inclui e remove questão', function () {
    $legislation = studyLegislation(10);
    $material = studyMaterial($legislation, 0, 9, 'draft');
    $admin = studyAdmin();

    // Inclui uma terceira questão
    $three = [...studyQuestions(), array_merge(studyQuestions()[1], ['board' => 'fcc', 'correct_answer' => 'B'])];

    $this->actingAs($admin)->put("/admin/study-materials/{$material->uuid}", adminEditPayload($material, ['questions' => $three]))
        ->assertSessionHasNoErrors();

    $material->refresh();
    expect($material->title)->toBe('Título revisado')
        ->and($material->summary)->toContain('revisado')
        ->and($material->questions)->toHaveCount(3)
        ->and($material->questions[2]->board)->toBe('fcc')
        ->and($material->questions[2]->position)->toBe(3);

    // Remove duas e troca a ordem não importa: fica só a FGV
    $this->actingAs($admin)->put("/admin/study-materials/{$material->uuid}", adminEditPayload($material, ['questions' => [studyQuestions()[1]]]))
        ->assertSessionHasNoErrors();

    expect($material->fresh()->questions)->toHaveCount(1)
        ->and($material->fresh()->questions[0]->board)->toBe('fgv');
});

test('a edição no admin passa pelas mesmas validações do agente', function () {
    $legislation = studyLegislation(20);
    $material = studyMaterial($legislation, 0, 9, 'draft');
    $admin = studyAdmin();
    $outside = studyBlockIds($legislation)[15];

    $badQuestion = [array_merge(studyQuestions()[0], ['type' => 'multiple_choice'])];

    $this->actingAs($admin)->put("/admin/study-materials/{$material->uuid}", adminEditPayload($material, ['questions' => $badQuestion]))
        ->assertSessionHasErrors('questions.0.type');

    $this->actingAs($admin)->put("/admin/study-materials/{$material->uuid}", adminEditPayload($material, ['questions' => []]))
        ->assertSessionHasErrors('questions');

    $this->actingAs($admin)->put("/admin/study-materials/{$material->uuid}", adminEditPayload($material, ['block_ids' => [$outside]]))
        ->assertSessionHasErrors('block_ids');

    $this->actingAs($admin)->put("/admin/study-materials/{$material->uuid}", adminEditPayload($material, ['title' => '']))
        ->assertSessionHasErrors('title');

    expect($material->fresh()->title)->toBe('Material 0-9');
});

test('publicar e despublicar', function () {
    $legislation = studyLegislation(10);
    $material = studyMaterial($legislation, 0, 9, 'draft');
    $admin = studyAdmin();

    $this->actingAs($admin)->post("/admin/study-materials/{$material->uuid}/publish")->assertSessionHas('success');
    expect($material->fresh()->status)->toBe('published')->and($material->fresh()->published_at)->not->toBeNull();

    $this->actingAs($admin)->post("/admin/study-materials/{$material->uuid}/unpublish");
    expect($material->fresh()->status)->toBe('draft')->and($material->fresh()->published_at)->toBeNull();
});

test('material desatualizado fica sinalizado, não publica e pode ser confirmado', function () {
    $legislation = studyLegislation(10);
    $material = studyMaterial($legislation, 0, 9, 'draft');
    $admin = studyAdmin();

    LegislationSegment::find($material->block_ids[3])->update(['original_text' => 'Art. 3 Texto novo da lei.']);

    $this->actingAs($admin)->get('/admin/study-materials?legislation='.$legislation->uuid)
        ->assertInertia(fn (AssertableInertia $page) => $page->where('materials.0.is_stale', true));

    $this->actingAs($admin)->get("/admin/study-materials/{$material->uuid}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/StudyMaterials/Edit')
            ->where('material.is_stale', true)
            ->has('blocks', 10)
            ->where('blocks.3.text', 'Art. 3 Texto novo da lei.'));

    $this->actingAs($admin)->post("/admin/study-materials/{$material->uuid}/publish")->assertSessionHas('error');
    expect($material->fresh()->status)->toBe('draft');

    // Salvar o conteúdo não confirma sozinho
    $this->actingAs($admin)->put("/admin/study-materials/{$material->uuid}", adminEditPayload($material));
    expect(app(StudyMaterialService::class)->isStale($material->fresh()))->toBeTrue();

    $this->actingAs($admin)->post("/admin/study-materials/{$material->uuid}/confirm")->assertSessionHas('success');
    expect(app(StudyMaterialService::class)->isStale($material->fresh()))->toBeFalse();

    $this->actingAs($admin)->post("/admin/study-materials/{$material->uuid}/publish")->assertSessionHas('success');
});

test('bloco coberto que deixou de existir deixa o material desatualizado', function () {
    $legislation = studyLegislation(10);
    $material = studyMaterial($legislation, 0, 9, 'published');
    $service = app(StudyMaterialService::class);

    LegislationSegment::find($material->block_ids[4])->delete();
    expect($service->isStale($material->fresh()))->toBeTrue();

    // Confirmar tira do material o bloco que sumiu
    $this->actingAs(studyAdmin())->post("/admin/study-materials/{$material->uuid}/confirm");
    expect($material->fresh()->block_ids)->toHaveCount(9)
        ->and($service->isStale($material->fresh()))->toBeFalse();

    // Sem o bloco de fronteira o material também fica desatualizado
    LegislationSegment::find($material->fresh()->last_block_id)->delete();
    expect($material->fresh()->last_block_id)->toBeNull()
        ->and($service->isStale($material->fresh()))->toBeTrue();
});

test('apagar no admin libera o trecho', function () {
    $legislation = studyLegislation(20);
    $material = studyMaterial($legislation, 0, 9, 'published');
    $skipped = studyMaterial($legislation, 10, 19, 'skipped');
    $admin = studyAdmin();
    $service = app(StudyMaterialService::class);

    expect($service->pendingCount($service->layout($legislation)))->toBe(0);

    $this->actingAs($admin)->delete("/admin/study-materials/{$skipped->uuid}")
        ->assertRedirect(route('admin.study-materials.index', ['legislation' => $legislation->uuid]));
    $this->actingAs($admin)->delete("/admin/study-materials/{$material->uuid}");

    expect(StudyMaterial::count())->toBe(0)
        ->and($service->pendingCount($service->layout($legislation)))->toBe(20);
});

test('o resumo vira HTML sem o HTML embutido e sem link inseguro', function () {
    $markdown = "## Título\n\nTexto **forte**.\n\n<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>\n\n[clique](javascript:alert(1))\n\n| a | b |\n|---|---|\n| 1 | 2 |";

    $html = $this->actingAs(studyAdmin())->postJson('/admin/study-materials/preview', ['summary' => $markdown])
        ->assertSuccessful()
        ->json('html');

    expect($html)->toContain('<h2>Título</h2>')
        ->toContain('<strong>forte</strong>')
        ->toContain('<table>')
        ->not->toContain('<script')
        ->not->toContain('<img')
        ->not->toContain('onerror')
        ->not->toContain('javascript:');
});

// ==================== Instalar a skill ====================

test('a página de instalação mostra a skill e os tokens do agente', function () {
    $admin = studyAdmin();
    $admin->createToken('mobile');
    $admin->createToken('Notebook', [StudyMaterialAgent::ABILITY]);

    $this->actingAs($admin)->get('/admin/study-materials/instalar')
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/StudyMaterials/Install')
            ->where('skillName', 'gerar-material-estudo')
            ->where('newToken', null)
            ->has('tokens', 1)
            ->where('tokens.0.name', 'Notebook')
            ->where('skillMarkdown', fn ($markdown) => str_contains($markdown, 'name: gerar-material-estudo')));
});

test('gerar token cria com a habilidade certa e mostra uma única vez', function () {
    $admin = studyAdmin();

    $response = $this->actingAs($admin)->post('/admin/study-materials/tokens', ['name' => 'Escritório']);
    $response->assertSessionHas('study_material_token');
    $plain = session('study_material_token');

    $token = $admin->tokens()->firstOrFail();
    expect($token->name)->toBe('Escritório')
        ->and($token->abilities)->toBe([StudyMaterialAgent::ABILITY]);

    // O token gerado abre as rotas do agente
    $this->app['auth']->forgetGuards();
    $this->withToken($plain)->getJson('/api/v1/admin/study-materials/legislations')->assertSuccessful();
});

test('revogar apaga só tokens do agente do próprio admin', function () {
    $admin = studyAdmin();
    $other = studyAdmin();

    $agent = $admin->createToken('Agente', [StudyMaterialAgent::ABILITY])->accessToken;
    $mobile = $admin->createToken('mobile')->accessToken;
    $foreign = $other->createToken('Agente', [StudyMaterialAgent::ABILITY])->accessToken;

    $this->actingAs($admin)->delete("/admin/study-materials/tokens/{$mobile->id}")->assertNotFound();
    $this->actingAs($admin)->delete("/admin/study-materials/tokens/{$foreign->id}")->assertNotFound();
    $this->actingAs($admin)->delete("/admin/study-materials/tokens/{$agent->id}")->assertSessionHas('success');

    expect($admin->tokens()->pluck('name')->all())->toBe(['mobile'])
        ->and($other->tokens()->count())->toBe(1);
});

test('o zip da skill traz a pasta com o SKILL.md', function () {
    $response = $this->actingAs(studyAdmin())->get('/admin/study-materials/baixar-skill')->assertSuccessful();

    $zip = new ZipArchive;
    expect($zip->open($response->baseResponse->getFile()->getPathname()))->toBeTrue()
        ->and($zip->locateName('gerar-material-estudo/SKILL.md'))->not->toBeFalse();
    $zip->close();
})->skip(! class_exists(ZipArchive::class), 'Extensão zip ausente.');

// ==================== Skill ====================

test('as duas cópias da skill são idênticas', function () {
    $claude = base_path('.claude/skills/gerar-material-estudo/SKILL.md');
    $agents = base_path('.agents/skills/gerar-material-estudo/SKILL.md');

    expect(file_exists($claude))->toBeTrue()
        ->and(file_exists($agents))->toBeTrue()
        ->and(file_get_contents($agents))->toBe(file_get_contents($claude));
});

test('a skill tem frontmatter com name e description', function () {
    $content = file_get_contents(base_path('.claude/skills/gerar-material-estudo/SKILL.md'));

    expect($content)->toStartWith("---\n")
        ->toMatch('/^name: gerar-material-estudo$/m')
        ->toMatch('/^description: .{40,}$/m');
});
