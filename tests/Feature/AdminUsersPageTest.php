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

    $response = get(route('admin.users.index'), [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertSuccessful();

    expect($response->json('component'))->toBe('admin/Users/Index');
    expect($response->json('props.stats.total_users'))->toBe(3);
    expect($response->json('props.stats.paying_users'))->toBe(1);
    expect($response->json('props.daily_growth'))->toHaveCount(20);
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
