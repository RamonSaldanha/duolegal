<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class LegislationTextService
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Busca o texto de uma legislação a partir da URL.
     *
     * Estratégia em cascata:
     * - Sites legislativos conhecidos (planalto.gov.br): scraping direto primeiro
     *   (Jina.ai trunca páginas grandes e tem problemas com encoding ISO-8859-1)
     * - Outros sites: Jina.ai primeiro, scraping direto como fallback
     */
    public function fetchText(string $url): string
    {
        // Sites legislativos conhecidos: scraping direto é mais confiável
        // (temos tratamento especializado para encoding, HTML malformado, etc.)
        if ($this->isKnownLegislativeSite($url)) {
            try {
                return $this->scrapeDirectly($url);
            } catch (\Exception $e) {
                // Fallback para Jina se scraping direto falhar
                $text = $this->tryJina($url);
                if ($text !== null) {
                    return $text;
                }

                throw $e;
            }
        }

        // Outros sites: Jina.ai primeiro (lida com JS, layouts complexos)
        $text = $this->tryJina($url);
        if ($text !== null) {
            return $text;
        }

        // Fallback para scraping direto
        return $this->scrapeDirectly($url);
    }

    /**
     * Verifica se a URL pertence a um site legislativo com HTML/encoding peculiar
     * onde scraping direto é mais confiável que Jina.ai.
     */
    private function isKnownLegislativeSite(string $url): bool
    {
        $knownDomains = [
            'planalto.gov.br',
        ];

        foreach ($knownDomains as $domain) {
            if (str_contains($url, $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tenta buscar via Jina.ai Reader API.
     * Retorna null se falhar ou se o texto estiver corrompido.
     */
    private function tryJina(string $url): ?string
    {
        try {
            $response = Http::timeout(30)
                ->withoutVerifying()
                ->withHeaders([
                    'X-Return-Format' => 'text',
                ])
                ->get('https://r.jina.ai/' . $url);

            if (!$response->successful() || strlen($response->body()) < 100) {
                return null;
            }

            $text = $response->body();

            // Detecta se Jina corrompeu o encoding (substituiu acentos por U+FFFD)
            if ($this->hasReplacementCharacters($text)) {
                return null; // Descarta — fallback para scraping direto
            }

            // Jina retorna markdown (links, bold, headings) que atrapalha detecção de dispositivos
            $text = $this->stripMarkdownFormatting($text);

            return $text;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Scraping direto com detecção de charset e conversão para UTF-8.
     */
    private function scrapeDirectly(string $url): string
    {
        $response = Http::timeout(30)
            ->withoutVerifying()
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException('Não foi possível acessar a URL: ' . $url);
        }

        $html = $response->body();

        if (empty($html)) {
            throw new \RuntimeException('O servidor retornou uma resposta vazia.');
        }

        // Converte encoding para UTF-8
        $html = $this->convertToUtf8($html, $response);

        $text = $this->htmlToPlainText($html);

        // Limpa referências legais e texto revogado
        $text = $this->cleanLegalReferences($text);

        // Remove itens completamente revogados: "I - ;" ou "I - ."
        $text = preg_replace('/^\s*[IVXLCDM]+\s*[-–]\s*[;.]\s*$/mu', '', $text);
        $text = preg_replace('/^\s*[a-z]\)\s*[;.]\s*$/mu', '', $text);

        // Corrige ordinal: "Art. 3 º" → "Art. 3º", "§ 1 º" → "§ 1º"
        $text = preg_replace('/(\d)\s+º/u', '$1º', $text);

        // Limpeza final de espaços
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n[ \t]+/u', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        if (empty($text)) {
            throw new \RuntimeException('Não foi possível extrair texto da URL.');
        }

        return $text;
    }

    /**
     * Converte HTML em texto plano limpo, tratando peculiaridades de sites legislativos.
     */
    private function htmlToPlainText(string $html): string
    {
        // Remove scripts, styles e texto vetado/revogado (strike, del)
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<(strike|del|s)\b[^>]*>.*?<\/\1>/is', '', $html);

        // Converte <sup> ordinal em º (planalto usa <sup><u>o</u></sup> para indicador ordinal)
        $html = preg_replace('/<sup[^>]*>\s*<u>\s*o\s*<\/u>\s*<\/sup>/i', 'º', $html);
        $html = preg_replace('/<sup[^>]*>\s*o\s*<\/sup>/i', 'º', $html);

        // Remove links de referências legais inteiros (o conteúdo é ruído: "Redação dada pela...")
        $html = preg_replace('/<a\b[^>]*>\s*\((?:Reda[çc][ãa]o|Vide|Inclu[ií]do|Alterado|Acrescentado|Revogado|Vig[êe]ncia)[^<]*<\/a>/ius', '', $html);

        // Corrige tags HTML malformadas do Planalto: <p<a name="..."> → <p><a name="...">
        // Sem isso, strip_tags() interpreta <p<a como tag não-fechada e descarta todo o resto
        $html = preg_replace('/<([a-z]+)</', '<$1><', $html);

        // Colapsa whitespace dentro do HTML antes de converter
        $html = preg_replace('/[\r\n\t]+/', ' ', $html);

        // Converte <br>, <p>, <div>, headings em quebras de linha
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/(p|div|h[1-6]|li|tr)>/i', "\n", $html);

        // Remove tags restantes
        $text = strip_tags($html);

        // Decodifica entidades HTML
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        return $text;
    }

    /**
     * Verifica se o texto contém U+FFFD (replacement character).
     * Indica que o encoding original foi corrompido durante a conversão.
     */
    private function hasReplacementCharacters(string $text): bool
    {
        // U+FFFD em UTF-8 = EF BF BD
        return str_contains($text, "\xEF\xBF\xBD");
    }

    /**
     * Detecta charset do HTML e converte para UTF-8.
     */
    private function convertToUtf8(string $html, Response $response): string
    {
        $charset = $this->detectCharset($html, $response);

        if ($charset && !in_array(strtoupper($charset), ['UTF-8', 'UTF8'], true)) {
            $converted = mb_convert_encoding($html, 'UTF-8', $charset);
            if ($converted !== false) {
                return $converted;
            }
        }

        // Se já é UTF-8 válido, retorna direto
        if (mb_check_encoding($html, 'UTF-8')) {
            return $html;
        }

        // Último recurso: tenta ISO-8859-1 → UTF-8
        return mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1');
    }

    /**
     * Detecta charset de múltiplas fontes: header HTTP, meta tags HTML, detecção automática.
     */
    private function detectCharset(string $html, Response $response): ?string
    {
        // 1. Header Content-Type
        $contentType = $response->header('Content-Type') ?? '';
        if (preg_match('/charset\s*=\s*([^\s;]+)/i', $contentType, $m)) {
            return trim($m[1], '"\'');
        }

        // 2. <meta charset="...">
        if (preg_match('/<meta\s+charset\s*=\s*["\']?\s*([^"\'\s>]+)/i', $html, $m)) {
            return trim($m[1]);
        }

        // 3. <meta http-equiv="Content-Type" content="...;charset=...">
        if (preg_match('/<meta\s+http-equiv\s*=\s*["\']?Content-Type["\']?\s+content\s*=\s*["\'][^"\']*charset\s*=\s*([^"\'\s;]+)/i', $html, $m)) {
            return trim($m[1]);
        }

        // 4. Detecção automática com lista de encodings comuns em sites BR
        $detected = mb_detect_encoding($html, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($detected && $detected !== 'UTF-8') {
            return $detected;
        }

        // 5. Se não detectou nada mas o conteúdo tem bytes > 0x7F, assume ISO-8859-1
        // (muito comum em sites governamentais brasileiros)
        if (preg_match('/[\x80-\xFF]/', $html)) {
            return 'ISO-8859-1';
        }

        return null;
    }

    /**
     * Remove referências legais desnecessárias do texto.
     */
    public function cleanLegalReferences(string $text): string
    {
        $patterns = [
            // Referências a Leis
            '/\(Reda[çc][ãa]o\s+dada\s+pel[ao]\s+[^)]*\)/ui',
            '/\(Vide\s+[^)]*\)/ui',
            '/\(Inclu[ií]do\s+pel[ao]\s+[^)]*\)/ui',
            '/\(Alterado\s+pel[ao]\s+[^)]*\)/ui',
            '/\(Acrescentado\s+pel[ao]\s+[^)]*\)/ui',
            '/\(Revogado\s+pel[ao]\s+[^)]*\)/ui',
            '/\(Revogado\)/ui',
            '/\(Vig[êe]ncia\)/ui',
            '/\(Regulamento\)/ui',
            '/\(VETADO\)/ui',
            // Regulamento como link text
            '/\bRegulamento\b(?=\s*$)/uim',
        ];

        $text = preg_replace($patterns, '', $text);

        // Colapsa espaços múltiplos (preservando quebras de linha)
        $text = preg_replace('/[^\S\n]+/', ' ', $text);
        $text = preg_replace('/\s+([,.;:])/', '$1', $text);

        return $text;
    }

    /**
     * Remove formatação markdown do texto (usado para limpar saída do Jina.ai).
     * Jina retorna links vazios [](URL), **bold**, # headings etc. que
     * impedem a detecção de dispositivos legais via regex.
     */
    public function stripMarkdownFormatting(string $text): string
    {
        // Remove markdown links: [text](url) ou [](url)
        $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text);

        // Remove bold: **text**
        $text = preg_replace('/\*\*(.+?)\*\*/us', '$1', $text);

        // Remove heading markers: # no início de linha
        $text = preg_replace('/^#{1,6}\s+/um', '', $text);

        // Remove horizontal rules: --- ou *** ou ___
        $text = preg_replace('/^[-*_]{3,}\s*$/um', '', $text);

        // Colapsa linhas em branco excedentes
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Identifica o tipo de uma linha de texto legal.
     */
    public function identifyType(string $line): string
    {
        if (preg_match('/^\s*Art\.?\s*\d+/iu', $line)) {
            return 'caput';
        }
        if (preg_match('/^\s*(§|Parágrafo\s+único)/iu', $line)) {
            return 'paragrafo';
        }
        if (preg_match('/^\s*[IVXLCDM]+\s*[-–]/u', $line)) {
            return 'inciso';
        }
        if (preg_match('/^\s*[a-z]\)/u', $line)) {
            return 'alinea';
        }
        if (preg_match('/^\s*\d+\)/u', $line)) {
            return 'item';
        }
        if (preg_match('/^\s*(TÍTULO|CAPÍTULO|SEÇÃO|SUBSEÇÃO|Livro|Parte)/iu', $line)) {
            return 'titulo';
        }

        return 'text';
    }

    /**
     * Extrai o marker estrutural de uma linha.
     */
    public function extractMarker(string $line): ?string
    {
        if (preg_match('/^(Art\.?\s*\d+[ºo]?\.?)/iu', $line, $m)) {
            return $m[1];
        }
        if (preg_match('/^(§\s*\d+[ºo]?\.?|Parágrafo\s+único\.?)/iu', $line, $m)) {
            return $m[1];
        }
        if (preg_match('/^([IVXLCDM]+)\s*[-–]/u', $line, $m)) {
            return $m[1];
        }
        if (preg_match('/^([a-z])\)/u', $line, $m)) {
            return $m[1] . ')';
        }
        if (preg_match('/^(\d+)\)/u', $line, $m)) {
            return $m[1] . ')';
        }

        return null;
    }

    /**
     * Extrai referência do artigo a partir do texto (primeira ocorrência).
     */
    public function extractArticleReference(string $text): ?string
    {
        if (preg_match('/Art\.?\s*(\d{1,3}(?:\.\d{3})*|\d+)\s*[ºo]?/iu', $text, $m)) {
            return str_replace('.', '', $m[1]);
        }

        return null;
    }

    /**
     * Verifica se o texto após um marcador de artigo/parágrafo indica uma referência cruzada.
     * Ex: "da Constituição", "do art. 5º", ", § 1º" → referência cruzada.
     * Ex: "São bens da União:", "Ao aplicar o ordenamento" → declaração real.
     *
     * A versão anterior marcava QUALQUER preposição como referência cruzada,
     * causando falsos positivos em artigos como "Art. 8º Ao aplicar...",
     * "Art. 15. Na ausência de normas...", "Art. 17. Para postular...".
     * Agora exige que a preposição seja seguida de uma referência legal concreta.
     */
    public function isCrossReferenceContext(string $textAfterAnchor): bool
    {
        $text = ltrim($textAfterAnchor);
        if ($text === '') {
            return false;
        }

        // Pontuação indicando continuação (não início de dispositivo)
        if (preg_match('/^[,;)\]]/', $text)) {
            return true;
        }

        // Preposição/contração seguida de referência legal concreta em até ~40 caracteres.
        // Captura: "da Constituição", "do Código Civil", "desta Lei", "no art. 5º", "pela Lei nº"
        // Não captura: "Na ausência de normas...", "Ao aplicar o ordenamento...", "Sem prejuízo..."
        return (bool) preg_match(
            '/^(da|do|das|dos|de|desta|deste|desse|dessa|dele|dela|deles|delas|ao|aos|à|às|na|no|nas|nos|nele|nela|neles|nelas|pela|pelo|pelas|pelos)\b.{0,40}(Lei\b|Código\b|Constituição|Emenda\b|Decreto\b|Medida\s+Provisória|Regulamento\b|Estatuto\b|art\.\s*\d|Art\.\s*\d|§\s*\d)/ius',
            $text,
        );
    }

    /**
     * Encontra a referência do artigo-pai buscando o último "Art. X" antes de uma posição.
     * Usado para atribuir article_reference a sub-dispositivos (incisos, parágrafos, alíneas).
     * Ignora referências cruzadas (ex: "art. 20 da Constituição").
     */
    public function findArticleReferenceAtPosition(string $rawText, int $position): ?string
    {
        $textBefore = mb_substr($rawText, 0, $position);

        // Captura número do artigo + texto após o marcador para verificar contexto
        $pattern = '/Art\.?\s*(\d{1,3}(?:\.\d{3})*|\d+)\s*[ºo°]?\s*\.?\s*[-–]?\s*(.*)/iu';

        if (preg_match_all($pattern, $textBefore, $matches, PREG_SET_ORDER)) {
            // Itera do último ao primeiro, pulando referências cruzadas
            for ($i = count($matches) - 1; $i >= 0; $i--) {
                if (!$this->isCrossReferenceContext($matches[$i][2])) {
                    return str_replace('.', '', $matches[$i][1]);
                }
            }
        }

        return null;
    }
}
