<?php

use App\Models\User;
use App\Services\StreakService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

afterEach(function () {
    Carbon::setTestNow();
});

function service(): StreakService
{
    return app(StreakService::class);
}

/**
 * Semeia uma conclusão de exercício (XpTransaction) numa data-calendário de SP.
 * Usa meio-dia UTC para garantir que a data UTC == data em America/Sao_Paulo.
 */
function seedCompletion(int $userId, string $date, int $times = 1): void
{
    $rows = [];
    for ($i = 0; $i < $times; $i++) {
        $rows[] = [
            'user_id' => $userId,
            'amount' => 10,
            'source_type' => 'play',
            'created_at' => $date.' 12:00:00',
        ];
    }
    DB::table('xp_transactions')->insert($rows);
}

test('ofensiva atual conta dias consecutivos terminando hoje', function () {
    Carbon::setTestNow('2026-05-30 12:00:00');
    $user = User::factory()->create();

    seedCompletion($user->id, '2026-05-28');
    seedCompletion($user->id, '2026-05-29');
    seedCompletion($user->id, '2026-05-30');

    expect(service()->currentStreak($user->id))->toBe(3);
});

test('ofensiva atual segue viva se jogou ontem mas ainda nao hoje', function () {
    Carbon::setTestNow('2026-05-30 12:00:00');
    $user = User::factory()->create();

    seedCompletion($user->id, '2026-05-28');
    seedCompletion($user->id, '2026-05-29');

    expect(service()->currentStreak($user->id))->toBe(2);
});

test('ofensiva atual zera quando o ultimo estudo foi anteontem', function () {
    Carbon::setTestNow('2026-05-30 12:00:00');
    $user = User::factory()->create();

    seedCompletion($user->id, '2026-05-27');
    seedCompletion($user->id, '2026-05-28');

    expect(service()->currentStreak($user->id))->toBe(0);
});

test('varios exercicios no mesmo dia nao inflam a ofensiva', function () {
    Carbon::setTestNow('2026-05-30 12:00:00');
    $user = User::factory()->create();

    seedCompletion($user->id, '2026-05-29', 4);
    seedCompletion($user->id, '2026-05-30', 7);

    expect(service()->currentStreak($user->id))->toBe(2);
});

test('recorde considera a maior sequencia historica mesmo apos quebrar', function () {
    Carbon::setTestNow('2026-05-30 12:00:00');
    $user = User::factory()->create();

    // Sequência de 4 dias em março
    foreach (['2026-03-01', '2026-03-02', '2026-03-03', '2026-03-04'] as $d) {
        seedCompletion($user->id, $d);
    }
    // Sequência atual de 2 dias
    seedCompletion($user->id, '2026-05-29');
    seedCompletion($user->id, '2026-05-30');

    expect(service()->longestStreak($user->id))->toBe(4)
        ->and(service()->currentStreak($user->id))->toBe(2);
});

test('getStats traz semana com intensidade e estado de hoje', function () {
    Carbon::setTestNow('2026-05-30 12:00:00'); // sábado
    $user = User::factory()->create();

    seedCompletion($user->id, '2026-05-29', 2); // sexta
    seedCompletion($user->id, '2026-05-30', 5); // sábado (hoje)

    $stats = service()->getStats($user->id);

    expect($stats['current_streak'])->toBe(2)
        ->and($stats['played_today'])->toBeTrue()
        ->and($stats['today_count'])->toBe(5)
        ->and($stats['week'])->toHaveCount(7);

    // Semana inicia na segunda; sexta é índice 4, sábado índice 5.
    expect($stats['week'][0]['weekday'])->toBe('Seg')
        ->and($stats['week'][4]['count'])->toBe(2)
        ->and($stats['week'][5]['count'])->toBe(5)
        ->and($stats['week'][5]['is_today'])->toBeTrue()
        ->and($stats['week'][6]['is_future'])->toBeTrue(); // domingo
});

test('getStats monta o calendario mensal com heatmap e navegacao', function () {
    Carbon::setTestNow('2026-05-30 12:00:00');
    $user = User::factory()->create();

    seedCompletion($user->id, '2026-05-10', 3);

    $stats = service()->getStats($user->id);
    $month = $stats['month'];

    expect($month['label'])->toBe('Maio 2026')
        ->and($month['year'])->toBe(2026)
        ->and($month['month'])->toBe(5)
        ->and($month['days'])->toHaveCount(31)
        ->and($month['next'])->toBeNull(); // mês atual: sem próximo

    $day10 = collect($month['days'])->firstWhere('day', 10);
    expect($day10['count'])->toBe(3)->and($day10['studied'])->toBeTrue();

    // Mês passado via parâmetro
    $april = service()->getStats($user->id, '2026-04')['month'];
    expect($april['label'])->toBe('Abril 2026')
        ->and($april['days'])->toHaveCount(30)
        ->and($april['next'])->toBe('2026-05');
});

test('endpoint GET /v1/streak retorna o payload de ofensiva', function () {
    Carbon::setTestNow('2026-05-30 12:00:00');
    $user = User::factory()->create();

    seedCompletion($user->id, '2026-05-29');
    seedCompletion($user->id, '2026-05-30');

    $this->actingAs($user)
        ->getJson('/api/v1/streak')
        ->assertOk()
        ->assertJsonPath('current_streak', 2)
        ->assertJsonPath('played_today', true)
        ->assertJsonStructure([
            'current_streak', 'longest_streak', 'played_today', 'today_count',
            'week' => [['date', 'weekday', 'count', 'studied', 'is_today', 'is_future']],
            'month' => ['year', 'month', 'label', 'first_weekday', 'prev', 'next', 'days'],
        ]);
});
