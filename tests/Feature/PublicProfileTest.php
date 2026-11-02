<?php

use App\Models\Discipline;
use App\Models\Legislation;
use App\Models\User;
use App\Services\DisciplineStarService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/** Disciplina com metas de 100, 500, 2000 e 5000 XP e o XP do usuário nela. */
function disciplineWithXp(User $user, string $name, int $xp): Discipline
{
    $legislation = Legislation::create(['title' => "Lei de {$name}", 'raw_text' => 'Art. 1º', 'status' => 'published']);
    $discipline = Discipline::create([
        'name' => $name,
        'icon' => 'Receipt',
        'color' => '#047857',
        'badge_key' => Str::slug($name),
        'star_thresholds' => [100, 500, 2000, 5000],
    ]);
    $discipline->legislations()->attach($legislation->id);

    if ($xp > 0) {
        $user->addXp($xp, 'test', null, $legislation->id);
    }

    return $discipline;
}

test('o perfil exige login', function () {
    $this->get('/perfil/'.User::factory()->create()->uuid)->assertRedirect('/login');
});

test('o perfil abre pelo uuid e não pelo id', function () {
    $other = User::factory()->create(['name' => 'Ana Maria da Silva', 'bio' => 'Estudo para a OAB']);

    $this->actingAs(User::factory()->create())
        ->get("/perfil/{$other->uuid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Profile/Show', false)
            ->where('profile.first_name', 'Ana')
            ->where('profile.last_name', 'Silva')
            ->where('profile.bio', 'Estudo para a OAB')
            ->where('profile.is_self', false));

    $this->actingAs(User::factory()->create())->get("/perfil/{$other->id}")->assertNotFound();
});

test('o perfil não entrega e-mail, nome completo nem XP', function () {
    $other = User::factory()->create(['name' => 'Ana Maria da Silva', 'email' => 'ana@example.com']);
    disciplineWithXp($other, 'Civil', 600);

    $response = $this->actingAs(User::factory()->create())->get("/perfil/{$other->uuid}");

    $response->assertInertia(fn ($page) => $page
        ->has('profile', 6)
        ->has('achievements', 1)
        ->has('achievements.0', 6)
        ->missing('achievements.0.total_xp')
        ->missing('achievements.0.next_star_xp'));

    $response->assertDontSee('ana@example.com');
    $response->assertDontSee('Ana Maria da Silva');
});

test('as conquistas batem com as da pessoa, só as com estrela e na mesma ordem', function () {
    $other = User::factory()->create();
    disciplineWithXp($other, 'Civil', 150);
    disciplineWithXp($other, 'Penal', 600);
    disciplineWithXp($other, 'Trabalhista', 0);

    $progress = app(DisciplineStarService::class)->progressFor($other);

    $this->actingAs(User::factory()->create())
        ->get("/perfil/{$other->uuid}")
        ->assertInertia(fn ($page) => $page
            ->has('achievements', 2)
            ->where('achievements.0.name', 'Penal')
            ->where('achievements.0.stars', $progress->firstWhere('name', 'Penal')['stars'])
            ->where('achievements.0.badge_url', $progress->firstWhere('name', 'Penal')['badge_url'])
            ->where('achievements.1.name', 'Civil')
            ->where('achievements.1.stars', $progress->firstWhere('name', 'Civil')['stars']));
});

test('o perfil não mostra a fantasia de quem deixou de assinar', function () {
    $other = User::factory()->create();
    $other->avatar_config = validAvatar(['outfit' => 'toga']);
    $other->save();

    $this->actingAs(User::factory()->create())
        ->get("/perfil/{$other->uuid}")
        ->assertInertia(fn ($page) => $page->where('profile.avatar_config.outfit', 'nenhum'));
});

test('o perfil mostra a ofensiva atual', function () {
    $other = User::factory()->create();
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get("/perfil/{$other->uuid}")
        ->assertInertia(fn ($page) => $page->where('profile.streak', 0));

    $other->addXp(10, 'test');

    $this->actingAs($viewer)
        ->get("/perfil/{$other->uuid}")
        ->assertInertia(fn ($page) => $page->where('profile.streak', 1));
});

test('a conquista escondida não chega à tela de quem visita', function () {
    $other = User::factory()->create();
    $civil = disciplineWithXp($other, 'Civil', 150);
    disciplineWithXp($other, 'Penal', 600);

    $this->actingAs($other)
        ->patch('/settings/achievements', ['hidden' => [$civil->id]])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    $response = $this->actingAs(User::factory()->create())->get("/perfil/{$other->uuid}");

    $response->assertInertia(fn ($page) => $page
        ->has('achievements', 1)
        ->where('achievements.0.name', 'Penal'));
    $response->assertDontSee($civil->uuid);
});

test('conquista nova aparece sem precisar marcar, e dá para mostrar de novo a escondida', function () {
    $user = User::factory()->create();
    $civil = disciplineWithXp($user, 'Civil', 150);

    $this->actingAs($user)->patch('/settings/achievements', ['hidden' => [$civil->id]]);
    disciplineWithXp($user, 'Penal', 600);

    $this->actingAs($user)
        ->get("/perfil/{$user->uuid}")
        ->assertInertia(fn ($page) => $page->has('achievements', 1)->where('achievements.0.name', 'Penal'));

    $this->actingAs($user)->patch('/settings/achievements', ['hidden' => []])->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get("/perfil/{$user->uuid}")
        ->assertInertia(fn ($page) => $page->has('achievements', 2));
});

test('as configurações listam só as conquistas ganhas e marcam as escondidas', function () {
    $user = User::factory()->create();
    $civil = disciplineWithXp($user, 'Civil', 150);
    disciplineWithXp($user, 'Penal', 600);
    disciplineWithXp($user, 'Trabalhista', 0);
    $user->hidden_achievements = [$civil->id];
    $user->save();

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertInertia(fn ($page) => $page
            ->has('achievements', 2)
            ->where('achievements.0.name', 'Penal')
            ->where('achievements.0.hidden', false)
            ->where('achievements.1.name', 'Civil')
            ->where('achievements.1.hidden', true));
});

test('escolher conquistas exige login e disciplina que existe', function () {
    $this->patch('/settings/achievements', ['hidden' => []])->assertRedirect('/login');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/achievements', ['hidden' => [999999]])
        ->assertSessionHasErrors('hidden.0');

    expect($user->fresh()->hidden_achievements)->toBeNull();
});

test('o próprio perfil é marcado', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get("/perfil/{$user->uuid}")
        ->assertInertia(fn ($page) => $page->where('profile.is_self', true));
});

test('a descrição é salva nas configurações e juntada em uma linha', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/profile', ['name' => $user->name, 'email' => $user->email, 'bio' => "  Estudo\n\n\n para   a OAB "])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->bio)->toBe('Estudo para a OAB');

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertInertia(fn ($page) => $page
            ->where('bio', 'Estudo para a OAB')
            ->where('profileUrl', route('profiles.show', $user->uuid)));
});

test('a descrição aceita até 160 caracteres', function () {
    $user = User::factory()->create();
    $fields = ['name' => $user->name, 'email' => $user->email];

    $this->actingAs($user)
        ->patch('/settings/profile', $fields + ['bio' => str_repeat('á', 160)])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->patch('/settings/profile', $fields + ['bio' => str_repeat('á', 161)])
        ->assertSessionHasErrors('bio');

    expect($user->fresh()->bio)->toBe(str_repeat('á', 160));
});

test('descrição vazia limpa o campo, e salvar sem enviá-la não a apaga', function () {
    $user = User::factory()->create(['bio' => 'Estudo para a OAB']);
    $fields = ['name' => $user->name, 'email' => $user->email];

    $this->actingAs($user)->patch('/settings/profile', $fields)->assertSessionHasNoErrors();
    expect($user->fresh()->bio)->toBe('Estudo para a OAB');

    $this->actingAs($user)->patch('/settings/profile', $fields + ['bio' => ''])->assertSessionHasNoErrors();
    expect($user->fresh()->bio)->toBeNull();
});

test('uuid, descrição e conquistas escondidas ficam fora do JSON cru do usuário', function () {
    $user = User::factory()->create(['bio' => 'Estudo para a OAB']);
    $user->hidden_achievements = [1];
    $user->save();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonMissingPath('uuid')
        ->assertJsonMissingPath('bio')
        ->assertJsonMissingPath('hidden_achievements');
});

test('o ranking do site traz o uuid e o da API continua sem ele', function () {
    $top = User::factory()->create();
    $top->addXp(50, 'test');
    $user = User::factory()->create();
    $user->addXp(10, 'test');

    $this->actingAs($user)
        ->get('/ranking')
        ->assertInertia(fn ($page) => $page
            ->where('topUsers.0.uuid', $top->uuid)
            ->where('currentUserData.uuid', $user->uuid));

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/ranking')
        ->assertOk()
        ->assertJsonMissingPath('top_users.0.uuid')
        ->assertJsonPath('top_users.0.id', $top->id);
});

test('o admin apaga a descrição e o admin vê o texto antes', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();
    $other = User::factory()->create(['bio' => 'Texto ofensivo']);

    $this->actingAs($admin)
        ->get("/admin/users/{$other->id}")
        ->assertInertia(fn ($page) => $page->where('user.bio', 'Texto ofensivo'));

    $this->actingAs($admin)->delete("/admin/users/{$other->id}/bio")->assertRedirect();

    expect($other->fresh()->bio)->toBeNull();
});

test('quem não é admin não apaga a descrição de outro usuário', function () {
    $other = User::factory()->create(['bio' => 'Estudo para a OAB']);

    $this->actingAs(User::factory()->create())
        ->delete("/admin/users/{$other->id}/bio")
        ->assertRedirect(route('play.map'));

    expect($other->fresh()->bio)->toBe('Estudo para a OAB');
});

test('a migração dá um uuid diferente a cada usuário que já existe', function () {
    $migration = require database_path('migrations/2026_11_02_200001_add_public_profile_to_users_table.php');
    $users = User::factory()->count(3)->create();

    $migration->down();
    $migration->up();

    $uuids = DB::table('users')->whereIn('id', $users->pluck('id'))->pluck('uuid');

    expect($uuids->filter()->unique())->toHaveCount(3);
});

test('todo usuário novo já nasce com uuid', function () {
    expect(Str::isUuid(User::factory()->create()->uuid))->toBeTrue();
});
