<?php

use App\Models\Legislation;
use App\Models\LegislationSegment;
use App\Models\SegmentLacuna;
use App\Models\User;
use App\Services\BlockBoundaryService;

function editorLegislation(): Legislation
{
    return Legislation::create([
        'title' => 'Lei de Teste',
        'status' => 'draft',
        'raw_text' => implode("\n", [
            'Art. 1º Todos são iguais perante a lei, sem distinção de qualquer natureza, garantindo-se a inviolabilidade do direito à vida.',
            'I - homens e mulheres são iguais em direitos e obrigações, nos termos desta Constituição;',
            'IX todos os julgamentos dos órgãos do Poder Judiciário serão públicos, sob pena de nulidade;',
            'Art. 2º São Poderes da União, independentes e harmônicos entre si, o Legislativo, o Executivo e o Judiciário.',
        ]),
    ]);
}

function editorBoundaries(Legislation $legislation): array
{
    return array_map(
        fn ($b) => ['char_start' => $b['char_start'], 'char_end' => $b['char_end']],
        app(BlockBoundaryService::class)->detectAllBoundaries($legislation->raw_text),
    );
}

beforeEach(function () {
    $this->admin = User::factory()->createOne(['is_admin' => true]);
    $this->legislation = editorLegislation();
});

test('cria blocos em lote e devolve só os blocos novos em JSON', function () {
    $boundaries = editorBoundaries($this->legislation);

    $response = $this->actingAs($this->admin)
        ->postJson(route('editor.store-blocks', $this->legislation->uuid), ['boundaries' => $boundaries]);

    $response->assertSuccessful();
    $segments = $response->json('segments');

    expect($segments)->toHaveCount(4)
        ->and(array_column($segments, 'segment_type'))->toBe(['caput', 'inciso', 'inciso', 'caput'])
        ->and(array_column($segments, 'article_reference'))->toBe(['1', '1', '1', '2'])
        ->and(LegislationSegment::count())->toBe(4);

    foreach ($segments as $segment) {
        $gaps = array_filter($segment['lacunas'], fn ($l) => $l['is_correct']);
        expect(count($gaps))->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(2);
    }
});

test('o lote ignora trechos que já viraram bloco', function () {
    $boundaries = editorBoundaries($this->legislation);

    $this->actingAs($this->admin)
        ->postJson(route('editor.store-blocks', $this->legislation->uuid), ['boundaries' => array_slice($boundaries, 0, 2)])
        ->assertSuccessful();

    $response = $this->actingAs($this->admin)
        ->postJson(route('editor.store-blocks', $this->legislation->uuid), ['boundaries' => $boundaries]);

    expect($response->json('segments'))->toHaveCount(2)
        ->and(LegislationSegment::count())->toBe(4);
});

test('desligar uma lacuna no editor oculta em vez de apagar, e ligar de novo reativa', function () {
    $segment = LegislationSegment::create([
        'legislation_id' => $this->legislation->id,
        'original_text' => 'Art. 2º São Poderes da União, independentes e harmônicos entre si.',
        'char_start' => 0,
        'char_end' => 66,
        'segment_type' => 'caput',
        'structural_marker' => 'Art. 2º',
        'position' => 1,
        'is_block' => true,
    ]);
    $lacuna = SegmentLacuna::create([
        'legislation_segment_id' => $segment->id,
        'word' => 'União,',
        'word_position' => 5,
        'is_correct' => true,
        'gap_order' => 1,
    ]);

    $toggle = fn () => $this->actingAs($this->admin)
        ->post(route('editor.toggle-lacuna', $segment->uuid), ['word' => 'União,', 'word_position' => 5]);

    $toggle()->assertRedirect();
    expect($lacuna->fresh()->is_active)->toBeFalse()
        ->and(SegmentLacuna::count())->toBe(1);

    $toggle()->assertRedirect();
    expect($lacuna->fresh()->is_active)->toBeTrue()
        ->and(SegmentLacuna::count())->toBe(1);
});

test('o editor informa quantos alunos concluíram cada bloco', function () {
    $segment = LegislationSegment::create([
        'legislation_id' => $this->legislation->id,
        'original_text' => 'Art. 1º Todos são iguais perante a lei.',
        'char_start' => 0,
        'char_end' => 39,
        'segment_type' => 'caput',
        'structural_marker' => 'Art. 1º',
        'position' => 1,
        'is_block' => true,
    ]);
    foreach (User::factory()->count(2)->create() as $student) {
        \App\Models\UserSegmentProgress::updateProgress($student->id, $segment->id, 1, 1, []);
    }

    $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $response = $this->actingAs($this->admin)
        ->get(route('editor.show', $this->legislation->uuid), ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version]);

    $response->assertOk();
    expect($response->json('props.segments.0.completed_count'))->toBe(2);
});
