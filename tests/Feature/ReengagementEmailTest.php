<?php

use App\Mail\ReengagementMail;
use App\Models\Legislation;
use App\Models\LegislationSegment;
use App\Models\ReengagementEmail;
use App\Models\SegmentLacuna;
use App\Models\User;
use App\Models\UserSegmentProgress;
use App\Services\ReengagementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Cashier\Subscription;

beforeEach(function () {
    Mail::fake();
    $this->travelTo(now()->startOfDay()->addHours(13));
});

/** Conta criada há tempos que jogou pela última vez há `$daysAgo` dias (nulo = nunca jogou). */
function reengagementUser(?int $daysAgo, array $attributes = []): User
{
    $user = User::factory()->createOne(array_merge(['created_at' => now()->subDays(400), 'lives' => 0], $attributes));

    if ($daysAgo !== null) {
        DB::table('xp_transactions')->insert([
            'user_id' => $user->id,
            'amount' => 10,
            'source_type' => 'legislation',
            'created_at' => now()->subDays($daysAgo),
        ]);
    }

    return $user;
}

function makePremium(User $user): void
{
    Subscription::query()->create([
        'user_id' => $user->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'quantity' => 1,
    ]);
}

function sentTo(User $user): array
{
    return ReengagementEmail::where('user_id', $user->id)->orderBy('id')->pluck('sequence')->all();
}

test('quem está parado há 15 dias recebe o 1º lembrete, e rodar de novo no mesmo dia não manda outro', function () {
    $user = reengagementUser(16);

    $this->artisan('emails:reengage')->assertSuccessful();
    $this->artisan('emails:reengage')->assertSuccessful();

    expect(sentTo($user))->toBe([1]);
    Mail::assertSent(ReengagementMail::class, 1);
});

test('conta antiga que nunca recebeu ganha só o 1º lembrete, sem os atrasados', function () {
    $user = reengagementUser(300);

    $this->artisan('emails:reengage')->assertSuccessful();
    $this->travel(1)->days();
    $this->artisan('emails:reengage')->assertSuccessful();

    expect(sentTo($user))->toBe([1]);
});

test('o intervalo cresce de 15 em 15 dias: 30 depois do 1º, 45 depois do 2º', function () {
    $user = reengagementUser(20);
    $this->artisan('emails:reengage');

    $this->travel(29)->days();
    $this->artisan('emails:reengage');
    expect(sentTo($user))->toBe([1]);

    $this->travel(1)->days();
    $this->artisan('emails:reengage');
    expect(sentTo($user))->toBe([1, 2]);

    $this->travel(44)->days();
    $this->artisan('emails:reengage');
    expect(sentTo($user))->toBe([1, 2]);

    $this->travel(1)->days();
    $this->artisan('emails:reengage');
    expect(sentTo($user))->toBe([1, 2, 3]);
});

test('voltar a jogar zera a sequência', function () {
    $user = reengagementUser(20);
    $this->artisan('emails:reengage');

    $this->travel(2)->days();
    DB::table('xp_transactions')->insert(['user_id' => $user->id, 'amount' => 10, 'source_type' => 'legislation', 'created_at' => now()]);

    $this->travel(14)->days();
    $this->artisan('emails:reengage');
    expect(sentTo($user))->toBe([1]);

    $this->travel(1)->days();
    $this->artisan('emails:reengage');
    expect(sentTo($user))->toBe([1, 1]);
});

test('quem joga, quem acabou de chegar e quem pediu para sair não recebem nada', function () {
    $active = reengagementUser(3);
    $newcomer = reengagementUser(null, ['created_at' => now()->subDays(5)]);
    $optedOut = reengagementUser(40, ['email_opt_out_at' => now()->subDay()]);
    $neverPlayed = reengagementUser(null, ['created_at' => now()->subDays(16)]);

    $this->artisan('emails:reengage')->assertSuccessful();

    expect(sentTo($active))->toBe([])
        ->and(sentTo($newcomer))->toBe([])
        ->and(sentTo($optedOut))->toBe([])
        ->and(sentTo($neverPlayed))->toBe([1]);
});

test('só tentar um bloco, mesmo errando, já conta como jogar', function () {
    $user = reengagementUser(null);
    $segment = boostSegment();

    $this->travel(-2)->days();
    UserSegmentProgress::updateProgress($user->id, $segment->id, 0, 2, []);
    $this->travel(2)->days();

    expect(User::withLastPlayed()->find($user->id)->lastPlayedAt()?->isSameDay(now()->subDays(2)))->toBeTrue();
});

test('assinante ganha boost de XP e quem joga de graça ganha vidas', function () {
    $free = reengagementUser(20);
    $premium = reengagementUser(20);
    makePremium($premium);

    $this->artisan('emails:reengage');

    expect(ReengagementEmail::where('user_id', $free->id)->value('gift'))->toBe(ReengagementEmail::GIFT_LIVES)
        ->and(ReengagementEmail::where('user_id', $premium->id)->value('gift'))->toBe(ReengagementEmail::GIFT_XP_BOOST);
});

test('o limite por execução vale e quem jogou mais recentemente vai primeiro', function () {
    $old = reengagementUser(200);
    $recent = reengagementUser(16);
    $middle = reengagementUser(60);

    $this->artisan('emails:reengage', ['--limit' => 2])->assertSuccessful();

    expect(sentTo($recent))->toBe([1])
        ->and(sentTo($middle))->toBe([1])
        ->and(sentTo($old))->toBe([]);
});

test('a simulação não manda nem grava nada', function () {
    reengagementUser(30);

    $this->artisan('emails:reengage', ['--dry-run' => true])->assertSuccessful();

    expect(ReengagementEmail::count())->toBe(0);
    Mail::assertNothingSent();
});

test('se o envio falhar, o lembrete não fica registrado', function () {
    $user = reengagementUser(30);
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP fora do ar'));

    $this->artisan('emails:reengage');

    expect(sentTo($user))->toBe([]);
});

test('abrir o presente dá 5 vidas uma vez só', function () {
    $user = reengagementUser(30, ['lives' => 1]);
    $this->artisan('emails:reengage');
    $email = ReengagementEmail::where('user_id', $user->id)->first();

    $this->get(route('gift.show', $email->uuid))->assertOk();
    $this->post(route('gift.claim', $email->uuid))->assertRedirect(route('gift.show', $email->uuid));
    $this->post(route('gift.claim', $email->uuid));

    expect($user->fresh()->lives)->toBe(1 + ReengagementService::GIFT_LIVES)
        ->and($email->fresh()->claimed_at)->not->toBeNull();
});

test('só o lembrete mais novo vale: o presente antigo expira', function () {
    $user = reengagementUser(20, ['lives' => 0]);
    $this->artisan('emails:reengage');
    $this->travel(30)->days();
    $this->artisan('emails:reengage');
    [$old, $new] = ReengagementEmail::where('user_id', $user->id)->orderBy('id')->get()->all();

    $this->post(route('gift.claim', $old->uuid));
    expect($user->fresh()->lives)->toBe(0);

    $this->post(route('gift.claim', $new->uuid));
    expect($user->fresh()->lives)->toBe(ReengagementService::GIFT_LIVES);
});

function boostSegment(): LegislationSegment
{
    $legislation = Legislation::create(['title' => 'Lei do Boost', 'raw_text' => 'Art. 1º', 'status' => 'published']);
    $segment = LegislationSegment::create([
        'legislation_id' => $legislation->id,
        'original_text' => 'Art. 1º A União e o Estado zelarão pela guarda da Constituição e das leis.',
        'char_start' => 0,
        'char_end' => 74,
        'segment_type' => 'caput',
        'structural_marker' => 'Art. 1º',
        'position' => 1,
        'is_block' => true,
    ]);
    foreach ([[3, 'União'], [6, 'Estado']] as $i => [$position, $word]) {
        SegmentLacuna::create(['legislation_segment_id' => $segment->id, 'word' => $word, 'word_position' => $position, 'is_correct' => true, 'gap_order' => $i + 1]);
    }

    return $segment;
}

test('o boost do assinante dá 50% a mais de XP por 3 dias, na web e no app', function (string $url) {
    $user = reengagementUser(30);
    makePremium($user);
    $this->artisan('emails:reengage');
    $this->post(route('gift.claim', ReengagementEmail::where('user_id', $user->id)->value('uuid')));

    expect($user->fresh()->xp_boost_until->isSameDay(now()->addDays(3)))->toBeTrue();

    $segment = boostSegment();
    $payload = [
        'segment_uuid' => $segment->uuid,
        'answers' => [
            ['gap_order' => 1, 'word_position' => 3, 'correct_word' => 'União', 'user_word' => 'União', 'is_correct' => true],
            ['gap_order' => 2, 'word_position' => 6, 'correct_word' => 'Estado', 'user_word' => 'Estado', 'is_correct' => true],
        ],
        'correct_count' => 2,
        'total_count' => 2,
    ];

    // 2 lacunas = 10 de XP; com o boost, 15
    $this->actingAs($user->fresh())->postJson($url, $payload)->assertJsonPath('xp_gained', 15);

    $this->travel(4)->days();
    $other = boostSegment();
    $this->actingAs($user->fresh())->postJson($url, array_merge($payload, ['segment_uuid' => $other->uuid]))->assertJsonPath('xp_gained', 10);
})->with(['web' => '/play/submit', 'api' => '/api/v1/play/submit']);

test('o e-mail leva o nome, a logo, o presente, o descadastro em um clique e nenhum travessão nem emoji', function () {
    $user = reengagementUser(30, ['name' => 'maria clara souza']);
    $user->onboarding_answers = ['goal' => 'oab', 'area' => 'penal', 'minutes' => 15];
    $user->save();

    foreach ([[ReengagementEmail::GIFT_LIVES, 1], [ReengagementEmail::GIFT_XP_BOOST, 1], [ReengagementEmail::GIFT_LIVES, 2], [ReengagementEmail::GIFT_LIVES, 5]] as [$gift, $sequence]) {
        $email = $user->reengagementEmails()->create(['sequence' => $sequence, 'gift' => $gift, 'inactive_days' => 30, 'sent_at' => now()]);
        $mail = new ReengagementMail($email);
        $html = $mail->render();
        $copy = implode(' ', array_filter($mail->copy()));

        expect($copy)->not->toContain('—')->not->toContain('–')->not->toContain(' - ')
            ->and($mail->envelope()->subject)->toContain('Maria')
            ->and($html)->toContain(route('gift.show', $email->uuid))
            ->and($html)->toContain('passar na OAB')
            ->and($html)->toContain(asset('img/email/logo.png'))
            ->and(preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B50}\x{FE0F}]/u', $html.$copy))->toBe(0)
            ->and($mail->headers()->text['List-Unsubscribe-Post'])->toBe('List-Unsubscribe=One-Click');
    }
});

test('descadastro pelo link assinado, inclusive o POST em um clique sem CSRF, e volta a receber', function () {
    $user = reengagementUser(30);
    $url = ReengagementMail::unsubscribeUrl($user);

    $this->get($url)->assertOk();
    $this->get(route('emails.unsubscribe', $user->id))->assertForbidden();

    $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertRedirect();
    expect($user->fresh()->receivesEmails())->toBeFalse();

    $this->artisan('emails:reengage');
    expect(sentTo($user))->toBe([]);

    $this->post(url(URL::signedRoute('emails.resubscribe', ['user' => $user->id], absolute: false)))->assertRedirect();
    expect($user->fresh()->receivesEmails())->toBeTrue();
});

test('quem está logado liga e desliga os lembretes nas configurações', function () {
    $user = User::factory()->createOne();

    $this->actingAs($user)->patch(route('emails.preferences'), ['receives_emails' => false])->assertRedirect(route('profile.edit'));
    expect($user->fresh()->email_opt_out_at)->not->toBeNull();

    $this->actingAs($user)->patch(route('emails.preferences'), ['receives_emails' => true]);
    expect($user->fresh()->email_opt_out_at)->toBeNull();
});

test('o JSON cru do usuário para o app continua igual', function () {
    $user = User::factory()->createOne(['email_opt_out_at' => now(), 'xp_boost_until' => now()->addDay()]);

    $json = $this->actingAs($user, 'sanctum')->getJson('/api/user')->json();

    expect($json)->not->toHaveKey('email_opt_out_at')->not->toHaveKey('xp_boost_until');
});
