<?php

use App\Models\LegalReference;
use App\Models\User;
use Laravel\Cashier\Subscription;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('admin users index returns stats and daily growth data', function () {
    $admin = User::factory()->createOne([
        'is_admin' => true,
        'created_at' => now()->subDays(2),
    ]);

    $payingUser = User::factory()->createOne([
        'created_at' => now()->subDays(1),
    ]);

    User::factory()->createOne([
        'created_at' => now(),
    ]);

    Subscription::query()->create([
        'user_id' => $payingUser->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'quantity' => 1,
        'trial_ends_at' => null,
        'ends_at' => null,
        'created_at' => now()->subDays(1),
        'updated_at' => now()->subDays(1),
    ]);

    /** @var User $admin */
    actingAs($admin);

    $response = get(route('admin.users.index'), adminInertiaHeaders());

    $response->assertSuccessful();

    expect($response->json('component'))->toBe('admin/Users/Index');
    expect($response->json('props.stats.total_users'))->toBe(3);
    expect($response->json('props.stats.paying_users'))->toBe(1);
    expect($response->json('props.daily_growth'))->toHaveCount(30);
    expect(get(route('admin.users.index', ['days' => 7]), adminInertiaHeaders())->json('props.daily_growth'))->toHaveCount(7);
});

function adminInertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
    ];
}

test('a lista traz objetivo, último jogo, lembretes e quem não recebe e-mail', function () {
    $admin = User::factory()->createOne(['is_admin' => true]);
    $player = User::factory()->createOne([
        'onboarding_answers' => ['goal' => 'oab', 'area' => 'penal', 'minutes' => 15],
        'email_opt_out_at' => now(),
    ]);
    \Illuminate\Support\Facades\DB::table('xp_transactions')->insert([
        'user_id' => $player->id, 'amount' => 25, 'source_type' => 'legislation', 'created_at' => now()->subDays(3),
    ]);
    $player->reengagementEmails()->create(['sequence' => 1, 'gift' => 'lives', 'inactive_days' => 20, 'sent_at' => now(), 'claimed_at' => now()]);

    actingAs($admin);
    $users = collect(get(route('admin.users.index'), adminInertiaHeaders())->json('props.users'))->keyBy('id');
    $row = $users[$player->id];

    expect($row['goal'])->toBe('oab')
        ->and($row['minutes'])->toBe(15)
        ->and($row['xp'])->toBe(25)
        ->and($row['receives_emails'])->toBeFalse()
        ->and($row['emails_sent'])->toBe(1)
        ->and($row['gifts_claimed'])->toBe(1)
        ->and($row['last_played_at'])->not->toBeNull()
        ->and($users[$admin->id]['last_played_at'])->toBeNull();
});

test('a ficha do usuário e a prévia do e-mail abrem para o admin', function () {
    $admin = User::factory()->createOne(['is_admin' => true]);
    $player = User::factory()->createOne(['onboarding_answers' => ['goal' => 'concurso', 'area' => 'administrativo', 'minutes' => 30]]);

    actingAs($admin);

    $show = get(route('admin.users.show', $player->id), adminInertiaHeaders());
    $show->assertSuccessful();
    expect($show->json('component'))->toBe('admin/Users/Show')
        ->and($show->json('props.user.goal'))->toBe('concurso');

    get(route('admin.emails.preview', ['gift' => 'xp_boost', 'sequence' => 2]))
        ->assertSuccessful()
        ->assertSee('XP turbinado');
});

test('quem não é admin é mandado de volta para o mapa', function () {
    $user = User::factory()->createOne();

    actingAs($user);

    get(route('admin.users.show', $user->id))->assertRedirect(route('play.map'));
    get(route('admin.emails.preview'))->assertRedirect(route('play.map'));
});

test('admin can export users to brevo compatible csv', function () {
    $admin = User::factory()->createOne([
        'name' => 'Admin User',
        'is_admin' => true,
    ]);

    $user = User::factory()->createOne([
        'name' => 'Emma Dubois',
        'email' => 'emma@example.com',
    ]);

    $legalReference = LegalReference::query()->create([
        'name' => 'Direito Penal',
    ]);

    $user->legalReferences()->attach($legalReference->id);

    /** @var User $admin */
    actingAs($admin);

    $response = get(route('admin.users.export-brevo'));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();

    expect($content)->toContain('"CONTACT ID","EMAIL","FIRSTNAME","LASTNAME","SMS","LANDLINE_NUMBER","WHATSAPP","INTERESTS"');
    expect($content)->toContain('emma@example.com');
    expect($content)->toContain('"Emma","Dubois"');
    expect($content)->toContain("['Direito Penal']");
});
