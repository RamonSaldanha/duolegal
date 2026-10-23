<?php

use App\Services\BlockBoundaryService;
use App\Services\LegislationTextService;

function detectBlocks(string $text): array
{
    return app(BlockBoundaryService::class)->detectAllBoundaries($text);
}

test('incisos sem hífen do Art. 93 da Constituição viram um bloco cada', function () {
    $text = implode("\n", [
        'Art. 93. Lei complementar, de iniciativa do Supremo Tribunal Federal, disporá sobre o Estatuto da Magistratura, observados os seguintes princípios:',
        'VIII-B - a permuta de magistrados de comarca de igual entrância, quando for o caso, e dentro do mesmo segmento de justiça, inclusive entre os juízes de segundo grau, vinculados a diferentes tribunais, na esfera da justiça estadual, federal ou do trabalho, atenderá, no que couber, ao disposto nas alíneas "a", "b", "c" e "e" do inciso II do caput deste artigo e no art. 94 desta Constituição;',
        'IX todos os julgamentos dos órgãos do Poder Judiciário serão públicos, e fundamentadas todas as decisões, sob pena de nulidade, podendo a lei limitar a presença, em determinados atos, às próprias partes e a seus advogados, ou somente a estes;',
        'X as decisões administrativas dos tribunais serão motivadas e em sessão pública, sendo as disciplinares tomadas pelo voto da maioria absoluta de seus membros;',
        'XI nos tribunais com número superior a vinte e cinco julgadores, poderá ser constituído órgão especial, com o mínimo de onze e o máximo de vinte e cinco membros, para o exercício das atribuições administrativas e jurisdicionais delegadas da competência do tribunal pleno;',
        'XV a distribuição de processos será imediata, em todos os graus de jurisdição.',
    ]);

    $blocks = detectBlocks($text);
    $incisos = array_values(array_filter($blocks, fn ($b) => $b['segment_type'] === 'inciso'));

    expect($incisos)->toHaveCount(5)
        ->and(array_map(fn ($b) => $b['structural_marker'], $incisos))->toBe(['VIII-B -', 'IX', 'X', 'XI', 'XV'])
        ->and($incisos[1]['original_text'])->toStartWith('IX todos os julgamentos')
        ->and($incisos[1]['original_text'])->not->toContain('X as decisões')
        ->and(array_unique(array_column($incisos, 'article_reference')))->toBe(['93']);
});

test('incisos com hífen continuam sendo detectados como antes', function () {
    $blocks = detectBlocks(implode("\n", [
        'Art. 5º Todos são iguais perante a lei, sem distinção de qualquer natureza, garantindo-se o seguinte:',
        'I - homens e mulheres são iguais em direitos e obrigações, nos termos desta Constituição, sem nenhuma exceção prevista;',
        'II – ninguém será obrigado a fazer ou deixar de fazer alguma coisa senão em virtude de lei, conforme previsto;',
    ]));

    expect(array_column($blocks, 'segment_type'))->toBe(['caput', 'inciso', 'inciso'])
        ->and($blocks[1]['structural_marker'])->toBe('I -')
        ->and($blocks[2]['structural_marker'])->toBe('II –');
});

test('linhas em maiúsculas não viram inciso', function () {
    $blocks = detectBlocks(implode("\n", [
        'Art. 1º Este Código regula os direitos e deveres que se estabelecem na ordem civil.',
        'LIVRO I',
        'CIVIL E COMERCIAL',
        'Art. 2º A personalidade civil da pessoa começa do nascimento com vida.',
    ]));

    expect(array_column($blocks, 'segment_type'))->toBe(['caput', 'caput']);
});

test('o serviço de texto reconhece o inciso sem hífen', function () {
    $service = app(LegislationTextService::class);

    expect($service->identifyType('IX todos os julgamentos serão públicos'))->toBe('inciso')
        ->and($service->extractMarker('IX todos os julgamentos serão públicos'))->toBe('IX')
        ->and($service->extractMarker('VIII-B - a permuta de magistrados'))->toBe('VIII-B')
        ->and($service->identifyType('LIVRO I'))->not->toBe('inciso');
});
