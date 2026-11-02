<?php

use App\Models\Discipline;
use App\Models\LawArticle;
use App\Models\LegalReference;
use App\Models\Legislation;
use App\Models\LegislationSegment;
use App\Models\SegmentLacuna;
use App\Models\User;
use App\Services\DisciplineStarService;
use App\Services\XpService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function lawWithBlocks(string $title, int $blocks, int $gapsPerBlock = 2): Legislation
{
    $legislation = Legislation::create(['title' => $title, 'raw_text' => 'Art. 1º', 'status' => 'published']);

    for ($i = 1; $i <= $blocks; $i++) {
        $segment = LegislationSegment::create([
            'legislation_id' => $legislation->id,
            'original_text' => "Art. {$i}º texto do artigo.",
            'char_start' => 0,
            'char_end' => 20,
            'segment_type' => 'caput',
            'structural_marker' => "Art. {$i}º",
            'position' => $i,
            'is_block' => true,
        ]);

        for ($gap = 1; $gap <= $gapsPerBlock; $gap++) {
            SegmentLacuna::create([
                'legislation_segment_id' => $segment->id,
                'word' => 'palavra',
                'word_position' => $gap,
                'is_correct' => true,
                'gap_order' => $gap,
            ]);
        }
    }

    return $legislation;
}

/** Disciplina com metas fixas de 100, 500, 2000 e 5000 XP. */
function disciplineWithThresholds(Legislation $legislation, array $attributes = []): Discipline
{
    $discipline = Discipline::create($attributes + [
        'name' => 'Tributário',
        'icon' => 'Receipt',
        'color' => '#047857',
        'badge_key' => 'tributario',
        'star_thresholds' => [100, 500, 2000, 5000],
    ]);
    $discipline->legislations()->attach($legislation->id);

    return $discipline;
}

function progressOf(User $user, Discipline $discipline): array
{
    return app(DisciplineStarService::class)->progressFor($user)->firstWhere('id', $discipline->id);
}

test('metas proporcionais à raiz do XP: matéria grande exige mais XP e fração menor', function () {
    $service = app(DisciplineStarService::class);

    expect($service->thresholdsFromPotential(9350))->toBe([210, 1052, 3156, 7012])
        ->and($service->thresholdsFromPotential(112810))->toBe([731, 3654, 10961, 24357])
        ->and($service->thresholdsFromPotential(0))->toBe([]);
});

test('primeiro XP dá 1 estrela e cada meta dá a estrela seguinte, no máximo 5', function (int $xp, int $stars) {
    expect(app(DisciplineStarService::class)->starsForXp($xp, [100, 500, 2000, 5000]))->toBe($stars);
})->with([
    [0, 0], [10, 1], [99, 1], [100, 2], [499, 2], [500, 3], [2000, 4], [4999, 4], [5000, 5], [90000, 5],
]);

test('potencial usa a fórmula do jogo: 5 XP por lacuna, mínimo de 10 por bloco', function () {
    $law = lawWithBlocks('Lei', 3, 4);
    lawWithBlocks('Lei fora da disciplina', 5);
    LegislationSegment::where('legislation_id', $law->id)->first()->lacunas()->delete();
    $discipline = disciplineWithThresholds($law);

    expect(app(DisciplineStarService::class)->potentials()[$discipline->id])->toBe(10 + 20 + 20);
});

test('adicionar lei não muda as metas nem tira estrelas', function () {
    $law = lawWithBlocks('CTN', 2);
    $discipline = disciplineWithThresholds($law);
    $user = User::factory()->createOne();
    $user->addXp(600, 'legislation', 1, $law->id);

    expect(progressOf($user, $discipline)['stars'])->toBe(3);

    $discipline->legislations()->attach(lawWithBlocks('Lei nova enorme', 50, 10)->id);

    expect($discipline->fresh()->star_thresholds)->toBe([100, 500, 2000, 5000])
        ->and(progressOf($user, $discipline)['stars'])->toBe(3);
});

test('remover lei ou subir a meta não tira estrela já conquistada', function () {
    $law = lawWithBlocks('CTN', 2);
    $other = lawWithBlocks('Lei de Execução Fiscal', 2);
    $discipline = disciplineWithThresholds($law);
    $discipline->legislations()->attach($other->id);
    $user = User::factory()->createOne();
    $user->addXp(600, 'legislation', 1, $other->id);
    app(XpService::class)->detectDisciplineLevelUp($user, $other->id, 600);

    $discipline->legislations()->detach($other->id);
    expect(progressOf($user, $discipline)['stars'])->toBe(3);

    $discipline->update(['star_thresholds' => [1000, 5000, 20000, 50000]]);
    expect(progressOf($user, $discipline)['stars'])->toBe(3);
});

test('ganho de estrela é detectado uma vez, com dados para a celebração', function () {
    $law = lawWithBlocks('CTN', 2);
    $discipline = disciplineWithThresholds($law);
    $user = User::factory()->createOne();
    $xp = app(XpService::class);

    $user->addXp(10, 'legislation', 1, $law->id);
    $first = $xp->detectDisciplineLevelUp($user, $law->id, 10);

    expect($first['discipline_id'])->toBe($discipline->id)
        ->and($first['old_level'])->toBe(0)
        ->and($first['new_level'])->toBe(1)
        ->and($first['stars'])->toBe(1)
        ->and($first['badge_url'])->toEndWith('/img/badges/tributario-1.png');

    $user->addXp(10, 'legislation', 2, $law->id);
    expect($xp->detectDisciplineLevelUp($user, $law->id, 10))->toBeNull();

    $user->addXp(90, 'legislation', 3, $law->id);
    expect($xp->detectDisciplineLevelUp($user, $law->id, 90)['stars'])->toBe(2);
});

test('lei em duas disciplinas registra a estrela nas duas', function () {
    $law = lawWithBlocks('CPC', 2);
    $civil = disciplineWithThresholds($law, ['name' => 'Civil']);
    $processo = disciplineWithThresholds($law, ['name' => 'Processo']);
    $user = User::factory()->createOne();

    $user->addXp(10, 'legislation', 1, $law->id);
    app(XpService::class)->detectDisciplineLevelUp($user, $law->id, 10);

    expect(DB::table('user_discipline_stars')->where('user_id', $user->id)->pluck('stars', 'discipline_id')->all())
        ->toEqual([$civil->id => 1, $processo->id => 1]);
});

test('a API segue compatível com o app instalado e acrescenta os campos novos', function () {
    $law = lawWithBlocks('CTN', 2);
    $discipline = disciplineWithThresholds($law);
    $empty = Discipline::create(['name' => 'Nova sem arte', 'icon' => 'Scale', 'color' => '#EAB308']);
    $user = User::factory()->createOne();
    $user->addXp(600, 'legislation', 1, $law->id);
    $user->addXp(9000, 'migration');
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/disciplines')->assertSuccessful();

    $item = collect($response->json('discipline_progress'))->firstWhere('id', $discipline->id);
    expect($item)->toMatchArray([
        'total_xp' => 600,
        'level' => 3,
        'current_xp_in_level' => 100,
        'xp_for_next_level' => 1500,
        'progress_percent' => 7,
        'stars' => 3,
        'next_star_xp' => 2000,
        'icon' => 'Receipt',
        'color' => '#047857',
    ])->and($item['badge_url'])->toEndWith('/img/badges/tributario-3.png');

    $locked = collect($response->json('discipline_progress'))->firstWhere('id', $empty->id);
    expect($locked)->toMatchArray(['total_xp' => 0, 'level' => 1, 'stars' => 0, 'badge_url' => null])
        ->and($locked['xp_for_next_level'])->toBeGreaterThan(0);

    // O nível global continua sem limite e soma todo o XP.
    expect($response->json('total_xp'))->toBe(9600)
        ->and($response->json('global_level.level'))->toBeGreaterThan(5);
});

test('com 5 estrelas a barra fica cheia e não há próxima meta', function () {
    $law = lawWithBlocks('CTN', 2);
    $discipline = disciplineWithThresholds($law);
    $user = User::factory()->createOne();
    $user->addXp(7000, 'legislation', 1, $law->id);

    expect(progressOf($user, $discipline))->toMatchArray([
        'level' => 5,
        'stars' => 5,
        'next_star_xp' => null,
        'progress_percent' => 100,
        'current_xp_in_level' => 3000,
        'xp_for_next_level' => 3000,
    ]);
});

test('a conversão simula sem gravar e, com --force, fixa metas, credita o XP antigo e não duplica', function () {
    $law = lawWithBlocks('Código Penal', 2);
    $discipline = Discipline::create(['name' => 'Criminal', 'icon' => 'Siren', 'color' => '#854D0E', 'badge_key' => 'criminal']);
    $discipline->legislations()->attach($law->id);

    $penal = LegalReference::create(['name' => 'Código penal', 'type' => 'code', 'is_active' => true]);
    $cdc = LegalReference::create(['name' => 'Código de Defesa do Consumidor', 'type' => 'code', 'is_active' => true]);
    $user = User::factory()->createOne();
    $user->addXp(1000, 'migration');

    foreach ([$penal, $penal, $penal, $cdc] as $index => $reference) {
        $article = LawArticle::create([
            'legal_reference_id' => $reference->id,
            'original_content' => 'Texto',
            'practice_content' => '_____',
            'article_reference' => (string) ($index + 1),
            'position' => $index + 1,
            'is_active' => true,
        ]);
        DB::table('user_progress')->insert([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'law_article_id' => $article->id,
            'is_completed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $this->artisan('disciplines:convert-to-stars')->assertSuccessful();
    expect($discipline->fresh()->star_thresholds)->toBeNull()
        ->and(DB::table('discipline_xp_credits')->count())->toBe(0);

    $this->artisan('disciplines:convert-to-stars --force')->assertSuccessful();
    $this->artisan('disciplines:convert-to-stars --force')->assertSuccessful();

    $thresholds = app(DisciplineStarService::class)->thresholdsFromPotential(20);

    expect($discipline->fresh()->star_thresholds)->toBe($thresholds)
        ->and(DB::table('discipline_xp_credits')->where('user_id', $user->id)->sole()->xp)->toBe(750)
        ->and(DB::table('user_discipline_stars')->where('user_id', $user->id)->sole()->stars)->toBe(5)
        ->and(progressOf($user, $discipline)['total_xp'])->toBe(750)
        ->and($user->fresh()->xp)->toBe(1000);
});

function badgeFormData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Direito Digital',
        'icon' => 'Cpu',
        'color' => '#1D4ED8',
        'badge_config' => ['icon' => 'estatuto', 'text' => 'Digitalista', 'shield' => '#3d8bf2', 'ribbon' => '#ffc21a'],
        'badge_images' => array_map(fn (int $stars) => UploadedFile::fake()->image("{$stars}.png", 16, 16), range(0, 5)),
    ], $overrides);
}

test('o admin cria disciplina com badge próprio, e as imagens ficam públicas', function () {
    Storage::fake('local');
    $law = lawWithBlocks('Marco Civil', 2);
    $admin = User::factory()->createOne(['is_admin' => true]);

    $this->actingAs($admin)
        ->post('/admin/disciplines', badgeFormData(['legislation_ids' => [$law->id]]))
        ->assertRedirect(route('admin.disciplines.index'));

    $discipline = Discipline::where('name', 'Direito Digital')->sole();
    $user = User::factory()->createOne();
    $url = progressOf($user, $discipline)['badge_url'];

    expect($discipline->badge_config['text'])->toBe('Digitalista')
        ->and($discipline->badge_version)->not->toBeNull()
        ->and($discipline->star_thresholds)->toBe(app(DisciplineStarService::class)->thresholdsFromPotential(20))
        ->and($url)->toContain("/discipline-badges/{$discipline->uuid}/0");

    auth()->logout();
    $this->get($url)->assertSuccessful()->assertHeader('content-type', 'image/png');
    $this->get("/discipline-badges/{$discipline->uuid}/9")->assertNotFound();
});

test('criar disciplina exige o badge completo', function () {
    Storage::fake('local');
    $admin = User::factory()->createOne(['is_admin' => true]);

    $this->actingAs($admin)
        ->post('/admin/disciplines', badgeFormData(['badge_images' => [UploadedFile::fake()->image('0.png')]]))
        ->assertSessionHasErrors('badge_images');

    $this->actingAs($admin)
        ->post('/admin/disciplines', badgeFormData(['badge_config' => ['icon' => 'inventada', 'text' => 'X', 'shield' => '#3d8bf2', 'ribbon' => '#ffc21a']]))
        ->assertSessionHasErrors('badge_config.icon');

    expect(Discipline::count())->toBe(0);
});

test('editar sem mexer no badge preserva a arte e as metas; trocar a arte muda a URL', function () {
    Storage::fake('local');
    $law = lawWithBlocks('CTN', 2);
    $discipline = disciplineWithThresholds($law);
    $admin = User::factory()->createOne(['is_admin' => true]);
    $original = app(DisciplineStarService::class)->badgeUrl($discipline, 3);

    $this->actingAs($admin)
        ->put("/admin/disciplines/{$discipline->uuid}", ['name' => 'Tributário', 'legislation_ids' => [$law->id, lawWithBlocks('LEF', 9)->id]])
        ->assertRedirect();

    $discipline->refresh();
    expect(app(DisciplineStarService::class)->badgeUrl($discipline, 3))->toBe($original)
        ->and($discipline->star_thresholds)->toBe([100, 500, 2000, 5000]);

    $this->actingAs($admin)
        ->put("/admin/disciplines/{$discipline->uuid}", badgeFormData(['name' => 'Tributário', 'legislation_ids' => [$law->id]]))
        ->assertRedirect();

    $changed = app(DisciplineStarService::class)->badgeUrl($discipline->refresh(), 3);
    expect($changed)->not->toBe($original)->toContain('/discipline-badges/');
    Storage::disk('local')->assertExists($discipline->badgeImagesDirectory().'/5.png');

    $this->actingAs($admin)->delete("/admin/disciplines/{$discipline->uuid}")->assertRedirect();
    Storage::disk('local')->assertMissing($discipline->badgeImagesDirectory().'/5.png');
});
