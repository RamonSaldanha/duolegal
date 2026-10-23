<?php

use App\Models\Legislation;
use App\Models\LegislationSegment;
use App\Models\SegmentLacuna;
use App\Models\User;
use App\Models\UserSegmentProgress;

function simplificationLegislation(): Legislation
{
    return Legislation::create(['title' => 'Lei de Teste', 'raw_text' => 'Art. 10.', 'status' => 'published']);
}

/**
 * Cria um bloco com lacunas nas posições (índice de palavra) informadas.
 *
 * @param  array<int, int>  $gapPositions
 * @param  array<int, string>  $distractors
 */
function simplificationSegment(Legislation $legislation, string $text, ?string $marker, array $gapPositions, array $distractors = [], int $charStart = 0): LegislationSegment
{
    $segment = LegislationSegment::create([
        'legislation_id' => $legislation->id,
        'original_text' => $text,
        'char_start' => $charStart,
        'char_end' => $charStart + mb_strlen($text),
        'segment_type' => 'caput',
        'structural_marker' => $marker,
        'position' => $charStart + 1,
        'is_block' => true,
    ]);

    $words = preg_split('/\s+/u', $text);
    foreach (array_values($gapPositions) as $i => $position) {
        SegmentLacuna::create([
            'legislation_segment_id' => $segment->id,
            'word' => $words[$position],
            'word_position' => $position,
            'is_correct' => true,
            'gap_order' => $i + 1,
        ]);
    }
    foreach ($distractors as $word) {
        SegmentLacuna::create([
            'legislation_segment_id' => $segment->id,
            'word' => $word,
            'word_position' => 0,
            'is_correct' => false,
        ]);
    }

    return $segment;
}

function activeWords(LegislationSegment $segment, bool $correct = true): array
{
    return $segment->activeLacunas()->where('is_correct', $correct)->orderBy('word_position')->pluck('word')->all();
}

beforeEach(function () {
    $this->legislation = simplificationLegislation();

    // Outros blocos da mesma lei tornam "contribuição" uma palavra familiar
    foreach ([1000, 2000, 3000] as $start) {
        simplificationSegment($this->legislation, 'Art. 11. A contribuição social será cobrada pelo ente competente na forma prevista.', 'Art. 11.', [3], [], $start);
    }

    // Art. 10: 20 palavras, 8 lacunas (uma no marcador e duas em nomes próprios)
    $this->segment = simplificationSegment(
        $this->legislation,
        'Art. 10. A União poderá instituir contribuição social mediante lei complementar, observado o disposto no regulamento expedido por Ulysses Guimarães.',
        'Art. 10.',
        [1, 3, 5, 6, 10, 15, 18, 19],
        ['soberania', 'tributo'],
        100,
    );
});

test('deixa no máximo 2 lacunas sem apagar nenhuma e sem tocar no progresso', function () {
    $user = User::factory()->createOne();
    $progress = UserSegmentProgress::updateProgress($user->id, $this->segment->id, 6, 8, [['gap_order' => 1]]);
    $progressBefore = $progress->fresh()->toArray();
    $rowsBefore = SegmentLacuna::count();

    $this->artisan('lacunas:simplify', ['--force' => true])->assertSuccessful();

    expect(SegmentLacuna::where('auto_added', false)->count())->toBe($rowsBefore)
        ->and(activeWords($this->segment))->toBe(['União', 'regulamento'])
        ->and($this->segment->lacunas()->where('is_correct', true)->where('is_active', false)->count())->toBe(6)
        ->and(count(activeWords($this->segment, false)))->toBeLessThanOrEqual(2)
        ->and($progress->fresh()->toArray())->toBe($progressBefore);
});

test('não escolhe palavras do marcador nem nomes próprios', function () {
    $this->artisan('lacunas:simplify', ['--force' => true])->assertSuccessful();

    expect(activeWords($this->segment))->not->toContain('10.')
        ->not->toContain('Ulysses')
        ->not->toContain('Guimarães.');
});

test('rodar de novo dá o mesmo resultado', function () {
    $this->artisan('lacunas:simplify', ['--force' => true])->assertSuccessful();
    $first = SegmentLacuna::orderBy('id')->get(['id', 'is_active'])->toArray();

    $this->artisan('lacunas:simplify', ['--force' => true])->assertSuccessful();

    expect(SegmentLacuna::orderBy('id')->get(['id', 'is_active'])->toArray())->toBe($first);
});

test('a simulação não grava nada', function () {
    $this->artisan('lacunas:simplify')->assertSuccessful();

    expect(SegmentLacuna::where('is_active', false)->count())->toBe(0)
        ->and(SegmentLacuna::where('auto_added', true)->count())->toBe(0);
});

test('bloco pequeno fica com 1 lacuna e 1 palavra falsa', function () {
    $small = simplificationSegment($this->legislation, 'Art. 12. É vedada a contribuição sobre proventos.', 'Art. 12.', [3, 5, 7], [], 200);

    $this->artisan('lacunas:simplify', ['--force' => true])->assertSuccessful();

    $distractors = activeWords($small, false);
    expect(activeWords($small))->toBe(['contribuição'])
        ->and($distractors)->toHaveCount(1)
        ->and(mb_strtolower($small->original_text))->not->toContain(mb_strtolower($distractors[0]));
});

test('nunca deixa um bloco com mais lacunas do que tinha', function () {
    $single = simplificationSegment(
        $this->legislation,
        'Art. 13. A lei disporá sobre a organização administrativa e judiciária dos Territórios, observado o regime próprio.',
        'Art. 13.',
        [7],
        [],
        300,
    );

    $this->artisan('lacunas:simplify', ['--force' => true])->assertSuccessful();

    expect(activeWords($single))->toHaveCount(1);
});

test('restore volta ao estado original', function () {
    $rowsBefore = SegmentLacuna::count();

    $this->artisan('lacunas:simplify', ['--force' => true])->assertSuccessful();
    $this->artisan('lacunas:simplify', ['--restore' => true, '--force' => true])->assertSuccessful();

    expect(SegmentLacuna::count())->toBe($rowsBefore)
        ->and(SegmentLacuna::where('is_active', false)->count())->toBe(0)
        ->and(SegmentLacuna::where('auto_added', true)->count())->toBe(0);
});

test('o jogo só envia as lacunas ativas', function () {
    $this->artisan('lacunas:simplify', ['--force' => true])->assertSuccessful();

    $format = function (LegislationSegment $segment) {
        return $this->formatSegmentForChallenge($segment);
    };

    foreach ([\App\Http\Controllers\LegislationPlayController::class, \App\Http\Controllers\Api\V1\PlayController::class] as $controller) {
        $formatted = $format->call(app($controller), $this->segment);

        expect(array_column($formatted['lacunas'], 'word'))->toBe(['União', 'regulamento'])
            ->and($formatted['total_gaps'])->toBe(2)
            ->and($formatted['options'])->not->toContain('instituir');
    }
});

test('sem lacuna aproveitável, escolhe uma palavra nova do texto e o restore a remove', function () {
    // Única lacuna está no marcador, que o jogo nem desenha
    $broken = simplificationSegment($this->legislation, 'Art. 14. A contribuição será exigida após noventa dias.', 'Art. 14.', [1], [], 400);

    $this->artisan('lacunas:simplify', ['--force' => true])->assertSuccessful();

    expect(activeWords($broken))->toBe(['contribuição'])
        ->and($broken->lacunas()->where('word', '14.')->value('is_active'))->toBeFalse()
        ->and($broken->lacunas()->where('word', 'contribuição')->value('auto_added'))->toBeTrue();

    $this->artisan('lacunas:simplify', ['--restore' => true, '--force' => true])->assertSuccessful();

    expect($broken->lacunas()->pluck('word')->all())->toBe(['14.']);
});

test('a quantidade de lacunas cresce com o tamanho do texto, com chances baixas para mais', function () {
    $service = app(\App\Services\LacunaAutoSelectionService::class);

    $distribution = function (int $words) use ($service) {
        $counts = [];
        for ($seed = 0; $seed < 1000; $seed++) {
            $gaps = $service->targetGapCount($words, $seed * 7919);
            $counts[$gaps] = ($counts[$gaps] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    };

    expect($service->targetGapCount(8, 123))->toBe(1)
        ->and($distribution(25))->toBe([2 => 1000])
        ->and(array_keys($distribution(60)))->toBe([2, 3])
        ->and(array_keys($distribution(100)))->toBe([2, 3, 4])
        ->and(array_keys($distribution(200)))->toBe([2, 3, 4, 5])
        ->and($distribution(200)[5])->toBeLessThan($distribution(200)[3])
        ->and($service->targetGapCount(200, 4242))->toBe($service->targetGapCount(200, 4242));
});

test('bloco grande com muitas lacunas pode ficar com mais de 2', function () {
    // 160 palavras com 12 lacunas: pela tabela, alguns textos desse tamanho ficam com 3 a 5
    $kept = [];
    foreach (range(1, 12) as $n) {
        $words = array_map(fn ($i) => 'termo'.chr(97 + $n).chr(97 + intdiv($i, 26)).chr(97 + $i % 26), range(1, 158));
        $text = 'Art. '.(500 + $n).'. '.implode(' ', $words).'.';
        $segment = simplificationSegment($this->legislation, $text, 'Art. '.(500 + $n).'.', range(2, 156, 13), [], 10000 * $n);
        $kept[] = app(\App\Services\LacunaAutoSelectionService::class)
            ->planSimplification($segment->load('lacunas'), [])['gaps'];
    }

    expect(max($kept))->toBeGreaterThan(2)
        ->and(min($kept))->toBeGreaterThanOrEqual(2);
});

test('lacunas de bloco novo são numeradas na ordem do texto', function () {
    // "direito" pontua mais que "lei", mas vem depois: a lacuna 1 tem de ser "lei"
    $text = 'IX todos os julgamentos dos órgãos do Poder Judiciário serão públicos, podendo a lei limitar a presença, em casos nos quais a preservação do direito à intimidade não prejudique o interesse público à informação;';
    $result = app(\App\Services\LacunaAutoSelectionService::class)->selectLacunas($text, ['direito' => 80, 'lei' => 60], 'IX');

    $positions = array_column($result['lacunas'], 'position');
    $sorted = $positions;
    sort($sorted);

    expect($positions)->toBe($sorted)
        ->and(array_column($result['lacunas'], 'gap_order'))->toBe(range(1, count($positions)));
});

test('o jogo numera as lacunas na ordem do texto mesmo com gap_order fora de ordem no banco', function () {
    $segment = simplificationSegment($this->legislation, 'Art. 20. A lei regulará o direito de petição e a forma de seu exercício perante os órgãos públicos.', 'Art. 20.', [3, 6], [], 500);
    // Inverte a numeração gravada, como nos blocos criados com o bug
    $segment->lacunas()->where('word_position', 3)->update(['gap_order' => 2]);
    $segment->lacunas()->where('word_position', 6)->update(['gap_order' => 1]);

    $format = function (LegislationSegment $segment) {
        return $this->formatSegmentForChallenge($segment);
    };

    foreach ([\App\Http\Controllers\LegislationPlayController::class, \App\Http\Controllers\Api\V1\PlayController::class] as $controller) {
        $lacunas = $format->call(app($controller), $segment)['lacunas'];

        expect(array_column($lacunas, 'word'))->toBe(['lei', 'direito'])
            ->and(array_column($lacunas, 'gap_order'))->toBe([1, 2]);
    }
});
