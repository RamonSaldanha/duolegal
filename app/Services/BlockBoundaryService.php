<?php

namespace App\Services;

class BlockBoundaryService
{
    private const MAX_GROUPED_CHARS = 300;
    private const MAX_GROUPED_ITEMS = 4;

    /**
     * Regex patterns para detectar inícios de dispositivos legais.
     * Cada padrão retorna: tipo do dispositivo e o marcador.
     */
    private const ANCHOR_PATTERNS = [
        // Art. 1º, Art. 1o, Art 1., Art. 100-A
        'artigo'    => '/^(Art\.?\s*\d+[ºo°]?(?:-[A-Z])?\s*\.?\s*[-–]?\s*)/ium',
        // § 1º, § 1o, § 2°
        'paragrafo' => '/^(§\s*\d+[ºo°]?\s*\.?\s*[-–]?\s*)/ium',
        // Parágrafo único
        'paragrafo_unico' => '/^(Parágrafo\s+único\s*\.?\s*[-–]?\s*)/ium',
        // I -, II -, III -, IV –, XIV -
        'inciso'    => '/^([IVXLCDM]+\s*[-–]\s*)/um',
        // a), b), c)
        'alinea'    => '/^([a-z]\)\s*)/um',
        // 1), 2), 3) (itens numéricos)
        'item'      => '/^(\d+\)\s*)/um',
    ];

    /**
     * Patterns para linhas estruturais (títulos, capítulos) que não viram blocos.
     */
    private const STRUCTURAL_PATTERNS = [
        '/^\s*(TÍTULO|CAPÍTULO|SEÇÃO|SUBSEÇÃO|LIVRO|PARTE)\s/ium',
        '/^\s*(Título|Capítulo|Seção|Subseção|Livro|Parte)\s/ium',
        // Linhas puramente "TÍTULO I" ou "CAPÍTULO II" etc.
        '/^\s*(TÍTULO|CAPÍTULO|SEÇÃO|SUBSEÇÃO|LIVRO|PARTE)\s+[IVXLCDM\d]+\s*$/ium',
    ];

    public function __construct(
        private LegislationTextService $textService,
    ) {}

    /**
     * Detecta todos os limites de bloco no texto para modo automático.
     * Cobre TODO o texto: inclui preâmbulo, incorpora linhas estruturais ao bloco seguinte.
     *
     * @return array<int, array{char_start: int, char_end: int, original_text: string, segment_type: string, article_reference: ?string, structural_marker: ?string}>
     */
    public function detectAllBoundaries(string $rawText): array
    {
        $anchors = $this->findAllAnchors($rawText);

        if (empty($anchors)) {
            return [];
        }

        $blocks = [];
        $textLength = mb_strlen($rawText);
        $anchorCount = count($anchors);

        // Inclui texto antes do primeiro anchor (preâmbulo, cabeçalho)
        if ($anchors[0]['offset'] > 0) {
            $preText = mb_substr($rawText, 0, $anchors[0]['offset']);
            if (mb_strlen(trim($preText)) > 10) {
                $blocks[] = [
                    'char_start' => 0,
                    'char_end' => $anchors[0]['offset'],
                    'original_text' => trim($preText),
                    'segment_type' => 'text',
                    'article_reference' => null,
                    'structural_marker' => null,
                ];
            }
        }

        // Rastreia o artigo atual para propagar aos sub-dispositivos
        $currentArticleRef = null;

        for ($i = 0; $i < $anchorCount; $i++) {
            $start = $anchors[$i]['offset'];
            $end = isset($anchors[$i + 1]) ? $anchors[$i + 1]['offset'] : $textLength;

            $blockText = mb_substr($rawText, $start, $end - $start);
            $blockText = rtrim($blockText);
            $end = $start + mb_strlen($blockText);

            // Pula blocos vazios
            if (mb_strlen(trim($blockText)) < 3) {
                continue;
            }

            // Atualiza artigo atual quando encontra um anchor de artigo
            if ($anchors[$i]['type'] === 'caput') {
                $currentArticleRef = $this->textService->extractArticleReference($blockText);
            }

            // Linhas estruturais (TÍTULO, CAPÍTULO): incorpora ao bloco seguinte
            if ($this->isStructuralLine($blockText) && $i + 1 < $anchorCount) {
                $nextEnd = isset($anchors[$i + 2]) ? $anchors[$i + 2]['offset'] : $textLength;
                $extendedText = mb_substr($rawText, $start, $nextEnd - $start);
                $extendedText = rtrim($extendedText);
                $nextEnd = $start + mb_strlen($extendedText);

                if (mb_strlen(trim($extendedText)) < 3) {
                    continue;
                }

                // O anchor seguinte pode ser um artigo — atualiza o contexto
                if ($anchors[$i + 1]['type'] === 'caput') {
                    $currentArticleRef = $this->textService->extractArticleReference($extendedText);
                }

                $cleanText = $this->textService->cleanLegalReferences($extendedText);

                $blocks[] = [
                    'char_start' => $start,
                    'char_end' => $nextEnd,
                    'original_text' => trim($cleanText),
                    'segment_type' => $anchors[$i + 1]['type'],
                    'article_reference' => $currentArticleRef,
                    'structural_marker' => $anchors[$i + 1]['marker'],
                ];

                $i++; // Pula o próximo anchor pois já foi incorporado
                continue;
            }

            $cleanText = $this->textService->cleanLegalReferences($blockText);

            $blocks[] = [
                'char_start' => $start,
                'char_end' => $end,
                'original_text' => trim($cleanText),
                'segment_type' => $anchors[$i]['type'],
                'article_reference' => $currentArticleRef,
                'structural_marker' => $anchors[$i]['marker'],
            ];
        }

        // Agrupa incisos/alíneas consecutivos curtos
        $blocks = $this->groupShortConsecutiveItems($blocks);

        return $blocks;
    }

    /**
     * Encontra todos os "anchors" (marcadores de início de dispositivo) no texto.
     *
     * Lida com raw_text que pode conter resquícios de markdown do Jina.ai
     * (links vazios [](URL), **bold**, etc.) stripando prefixos não-legais
     * de cada linha antes de aplicar os regex, e ajustando o offset.
     *
     * @return array<int, array{offset: int, type: string, marker: string, line: string}>
     */
    private function findAllAnchors(string $rawText): array
    {
        $anchors = [];
        $lines = preg_split('/\n/', $rawText);
        $currentOffset = 0;

        foreach ($lines as $line) {
            $trimmedLine = ltrim($line);
            $leadingSpaces = mb_strlen($line) - mb_strlen($trimmedLine);

            // Strip prefixos de markdown residual (ex: "[](URL)", "**", "#")
            // Preserva: letras (inc. acentuadas), dígitos, § (parágrafo)
            $markdownLen = 0;
            if (preg_match('/^[^a-zA-ZÀ-ÿ0-9§]+/u', $trimmedLine, $prefixMatch)) {
                $markdownLen = mb_strlen($prefixMatch[0]);
                $trimmedLine = mb_substr($trimmedLine, $markdownLen);
            }

            foreach (self::ANCHOR_PATTERNS as $type => $pattern) {
                if (preg_match($pattern, $trimmedLine, $m)) {
                    // Para artigos e parágrafos, verifica se é referência cruzada
                    // Ex: "art. 20 da Constituição" ou "§ 1º do art. 5º" não são dispositivos reais
                    if (in_array($type, ['artigo', 'paragrafo', 'paragrafo_unico'])) {
                        $remainder = mb_substr($trimmedLine, mb_strlen($m[1]));
                        if ($this->textService->isCrossReferenceContext($remainder)) {
                            break; // Não é um anchor real, pula esta linha
                        }
                    }

                    $anchors[] = [
                        'offset' => $currentOffset + $leadingSpaces + $markdownLen,
                        'type' => $this->normalizeType($type),
                        'marker' => trim($m[1]),
                        'line' => $trimmedLine,
                    ];
                    break; // Um anchor por linha
                }
            }

            // +1 para o \n
            $currentOffset += mb_strlen($line) + 1;
        }

        // Ordena por offset
        usort($anchors, fn($a, $b) => $a['offset'] - $b['offset']);

        return $anchors;
    }

    /**
     * Normaliza tipo de anchor para tipos de segmento do sistema.
     */
    private function normalizeType(string $anchorType): string
    {
        return match ($anchorType) {
            'artigo' => 'caput',
            'paragrafo', 'paragrafo_unico' => 'paragrafo',
            'inciso' => 'inciso',
            'alinea' => 'alinea',
            'item' => 'item',
            default => 'text',
        };
    }

    /**
     * Verifica se uma linha é estrutural (título, capítulo, seção).
     */
    private function isStructuralLine(string $text): bool
    {
        $firstLine = strtok(trim($text), "\n");

        foreach (self::STRUCTURAL_PATTERNS as $pattern) {
            if (preg_match($pattern, $firstLine)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Agrupa incisos/alíneas consecutivos curtos em um único bloco.
     */
    private function groupShortConsecutiveItems(array $blocks): array
    {
        if (count($blocks) <= 1) {
            return $blocks;
        }

        $grouped = [];
        $buffer = null;
        $bufferCount = 0;

        foreach ($blocks as $block) {
            $isGroupable = in_array($block['segment_type'], ['inciso', 'alinea', 'item']);
            $isShort = mb_strlen($block['original_text']) < 80;

            if ($isGroupable && $isShort) {
                if ($buffer === null) {
                    $buffer = $block;
                    $bufferCount = 1;
                } elseif (
                    $bufferCount < self::MAX_GROUPED_ITEMS
                    && mb_strlen($buffer['original_text']) + mb_strlen($block['original_text']) < self::MAX_GROUPED_CHARS
                    && in_array($buffer['segment_type'], ['inciso', 'alinea', 'item'])
                ) {
                    // Agrupa: estende o buffer
                    $buffer['char_end'] = $block['char_end'];
                    $buffer['original_text'] .= "\n" . $block['original_text'];
                    $bufferCount++;
                } else {
                    // Buffer cheio, flush e começa novo
                    $grouped[] = $buffer;
                    $buffer = $block;
                    $bufferCount = 1;
                }
            } else {
                // Não agrupável: flush buffer e adiciona bloco
                if ($buffer !== null) {
                    $grouped[] = $buffer;
                    $buffer = null;
                    $bufferCount = 0;
                }
                $grouped[] = $block;
            }
        }

        // Flush final
        if ($buffer !== null) {
            $grouped[] = $buffer;
        }

        return $grouped;
    }
}
