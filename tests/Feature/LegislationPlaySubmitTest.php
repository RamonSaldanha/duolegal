<?php

use App\Models\Legislation;
use App\Models\LegislationSegment;
use App\Models\SegmentLacuna;
use App\Models\User;
use App\Models\UserSegmentProgress;

function twoGapSegment(): LegislationSegment
{
    $legislation = Legislation::create(['title' => 'Lei de Teste', 'raw_text' => 'Art. 1º', 'status' => 'published']);
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
        SegmentLacuna::create([
            'legislation_segment_id' => $segment->id,
            'word' => $word,
            'word_position' => $position,
            'is_correct' => true,
            'gap_order' => $i + 1,
        ]);
    }

    return $segment;
}

function submitPayload(LegislationSegment $segment, bool $secondCorrect): array
{
    return [
        'segment_uuid' => $segment->uuid,
        'answers' => [
            ['gap_order' => 1, 'word_position' => 3, 'correct_word' => 'União', 'user_word' => 'Estado', 'is_correct' => false],
            ['gap_order' => 2, 'word_position' => 6, 'correct_word' => 'Estado', 'user_word' => $secondCorrect ? 'Estado' : 'União', 'is_correct' => $secondCorrect],
        ],
        'correct_count' => $secondCorrect ? 1 : 0,
        'total_count' => 2,
    ];
}

test('regra de aprovação: pode errar no máximo 1 lacuna, sem passar dos 70% antigos', function () {
    expect(UserSegmentProgress::requiredCorrect(1))->toBe(1)
        ->and(UserSegmentProgress::requiredCorrect(2))->toBe(1)
        ->and(UserSegmentProgress::requiredCorrect(3))->toBe(2)
        ->and(UserSegmentProgress::requiredCorrect(4))->toBe(3)
        ->and(UserSegmentProgress::requiredCorrect(5))->toBe(4)
        ->and(UserSegmentProgress::requiredCorrect(10))->toBe(7);
});

test('acertar 1 de 2 aprova, não tira vida e dá XP', function (string $url) {
    $segment = twoGapSegment();
    $user = User::factory()->createOne(['lives' => 3]);

    $response = $this->actingAs($user)->postJson($url, submitPayload($segment, true));

    $response->assertSuccessful()
        ->assertJsonPath('passed', true)
        ->assertJsonPath('lost_life', false)
        ->assertJsonPath('progress.is_completed', true);

    expect($response->json('xp_gained'))->toBeGreaterThan(0)
        ->and($user->fresh()->lives)->toBe(3);
})->with(['web' => '/play/submit', 'api' => '/api/v1/play/submit']);

test('errar as 2 reprova e tira uma vida', function (string $url) {
    $segment = twoGapSegment();
    $user = User::factory()->createOne(['lives' => 3]);

    $response = $this->actingAs($user)->postJson($url, submitPayload($segment, false));

    $response->assertSuccessful()
        ->assertJsonPath('passed', false)
        ->assertJsonPath('lost_life', true);

    expect($response->json('progress.is_completed'))->toBeFalsy()
        ->and($user->fresh()->lives)->toBe(2);
})->with(['web' => '/play/submit', 'api' => '/api/v1/play/submit']);
