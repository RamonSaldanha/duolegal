<?php

namespace App\Services;

class LacunaAutoSelectionService
{
    /**
     * Seleciona lacunas automaticamente para um texto.
     * Portado do algoritmo N8N (avaliarPalavra + calcularNumeroLacunas).
     *
     * @return array{lacunas: array<int, array{word: string, position: int, gap_order: int}>, distractors: array<int, array{word: string}>}
     */
    public function selectLacunas(string $text): array
    {
        $tokens = $this->tokenize($text);
        $words = $this->extractWords($tokens);

        if (empty($words)) {
            return ['lacunas' => [], 'distractors' => []];
        }

        $numGaps = $this->calculateGapCount($text, count($words));

        // Pontua e seleciona palavras
        $candidates = $this->scoreCandidates($words);
        $selected = $this->selectTopCandidates($candidates, $numGaps);

        // Ordena por posição para atribuir gap_order
        usort($selected, fn($a, $b) => $a['token_index'] - $b['token_index']);

        $lacunas = [];
        foreach ($selected as $i => $sel) {
            $lacunas[] = [
                'word' => $sel['original'],
                'position' => $sel['word_index'],
                'gap_order' => $i + 1,
            ];
        }

        // Gera distratores
        $correctWords = array_map(fn($l) => mb_strtolower($this->cleanWord($l['word'])), $lacunas);
        $distractors = $this->getDistractorWords($correctWords, $numGaps);

        return [
            'lacunas' => $lacunas,
            'distractors' => $distractors,
        ];
    }

    /**
     * Tokeniza texto preservando whitespace.
     *
     * @return array<int, string>
     */
    private function tokenize(string $text): array
    {
        $tokens = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        return array_values(array_filter($tokens, fn($t) => $t !== ''));
    }

    /**
     * Extrai apenas palavras (não-espaço) com seus índices.
     *
     * @return array<int, array{text: string, clean: string, token_index: int, word_index: int}>
     */
    private function extractWords(array $tokens): array
    {
        $words = [];
        $wordIndex = 0;

        foreach ($tokens as $i => $token) {
            if (!preg_match('/^\s+$/u', $token)) {
                $words[] = [
                    'text' => $token,
                    'clean' => mb_strtolower($this->cleanWord($token)),
                    'token_index' => $i,
                    'word_index' => $wordIndex,
                ];
                $wordIndex++;
            }
        }

        return $words;
    }

    /**
     * Remove pontuação de uma palavra.
     */
    private function cleanWord(string $word): string
    {
        return preg_replace('/[.,;:()\[\]"\']/u', '', $word);
    }

    /**
     * Calcula o número ideal de lacunas baseado na contagem de palavras.
     * Inclui variação aleatória para que desafios similares tenham dificuldades diferentes.
     *
     * Referência de faixas:
     *   < 10 palavras  → 1 lacuna
     *   10-15 palavras → 1-2 lacunas
     *   16-21 palavras → 2-3 lacunas
     *   22-34 palavras → 3-4 lacunas (mínimo 3)
     *   35-49 palavras → ~10% das palavras
     *   50+   palavras → ~12% das palavras
     */
    private function calculateGapCount(string $text, int $wordCount): int
    {
        $roll = random_int(1, 100);

        if ($wordCount < 10) {
            // Inciso curto, frase simples: sempre 1
            $numGaps = 1;
        } elseif ($wordCount < 16) {
            // Texto curto: base 1, ~35% chance de 2
            $numGaps = ($roll <= 35) ? 2 : 1;
        } elseif ($wordCount < 22) {
            // Texto médio-curto: base 2, ~35% chance de 3
            $numGaps = ($roll <= 35) ? 3 : 2;
        } elseif ($wordCount < 35) {
            // Texto médio: base 3, ~35% chance de 4
            $numGaps = ($roll <= 35) ? 4 : 3;
        } elseif ($wordCount < 50) {
            // Texto grande: proporcional ~10%
            $base = max(4, (int) floor($wordCount * 0.10));
            $numGaps = ($roll <= 30) ? $base + 1 : $base;
        } else {
            // Texto muito grande: proporcional ~12%
            $base = max(5, (int) floor($wordCount * 0.12));
            $numGaps = ($roll <= 25) ? $base + 1 : $base;
        }

        // Cap em 15% das palavras para não ficar excessivo
        return min($numGaps, max(1, (int) floor($wordCount * 0.15)));
    }

    /**
     * Pontua palavras candidatas para seleção como lacuna.
     *
     * @return array<int, array{score: int, original: string, clean: string, token_index: int, word_index: int}>
     */
    private function scoreCandidates(array $words): array
    {
        $candidates = [];

        foreach ($words as $word) {
            $clean = $word['clean'];

            // Exclusões
            if (mb_strlen($clean) < 3) {
                continue;
            }
            if (in_array($clean, LegalVocabulary::STOP_WORDS, true)) {
                continue;
            }
            if (preg_match('/^\d+$/u', $clean)) {
                continue;
            }
            if (preg_match('/^[^\w]+$/u', $clean)) {
                continue;
            }

            // Pontuação
            $score = 1;

            // Substantivos jurídicos
            if (in_array($clean, LegalVocabulary::HIGH_VALUE_NOUNS, true)) {
                $score += 3;
            }

            // Comprimento
            if (mb_strlen($clean) >= 8) {
                $score += 2;
            } elseif (mb_strlen($clean) >= 6) {
                $score += 1;
            }

            // Sufixos jurídicos
            if (preg_match('/(ção|dade|mento|ncia)$/u', $clean)) {
                $score += 1;
            }

            $candidates[] = [
                'score' => $score,
                'original' => $word['text'],
                'clean' => $clean,
                'token_index' => $word['token_index'],
                'word_index' => $word['word_index'],
            ];
        }

        return $candidates;
    }

    /**
     * Seleciona as melhores palavras sem duplicatas.
     *
     * @return array<int, array{original: string, clean: string, token_index: int, word_index: int}>
     */
    private function selectTopCandidates(array $candidates, int $numGaps): array
    {
        // Ordena por pontuação desc com aleatoriedade para empates
        usort($candidates, function ($a, $b) {
            $diff = $b['score'] - $a['score'];
            if ($diff !== 0) {
                return $diff;
            }

            return random_int(-1, 1);
        });

        $selected = [];
        $usedWords = [];

        foreach ($candidates as $candidate) {
            if (count($selected) >= $numGaps) {
                break;
            }

            // Sem duplicatas
            if (in_array($candidate['clean'], $usedWords, true)) {
                continue;
            }

            // Anti-clumping: verifica proximidade com lacunas já selecionadas
            $tooClose = false;
            foreach ($selected as $sel) {
                if (abs($sel['word_index'] - $candidate['word_index']) <= 2) {
                    $tooClose = true;
                    break;
                }
            }

            if ($tooClose && count($selected) < $numGaps - 1) {
                // Pula se tem candidatos suficientes restantes, aceita se é o último
                continue;
            }

            $selected[] = $candidate;
            $usedWords[] = $candidate['clean'];
        }

        return $selected;
    }

    /**
     * Gera palavras distratoras a partir da lista de vocabulário jurídico.
     * Quantidade escala com o número de lacunas, com variação aleatória.
     *
     * @param array $correctWords Palavras corretas (lowercase, sem pontuação)
     * @param int $gapCount Número de lacunas no bloco
     * @return array<int, array{word: string}>
     */
    public function getDistractorWords(array $correctWords, int $gapCount): array
    {
        $roll = random_int(1, 100);

        if ($gapCount <= 1) {
            // 1 lacuna: 60% sem distrator, 30% com 1, 10% com 2
            $numDistractors = ($roll <= 60) ? 0 : (($roll <= 90) ? 1 : 2);
        } elseif ($gapCount <= 2) {
            // 2 lacunas: 30% sem, 50% com 1, 20% com 2
            $numDistractors = ($roll <= 30) ? 0 : (($roll <= 80) ? 1 : 2);
        } elseif ($gapCount <= 3) {
            // 3 lacunas: 50% com 1, 50% com 2
            $numDistractors = ($roll <= 50) ? 1 : 2;
        } elseif ($gapCount <= 5) {
            // 4-5 lacunas: 60% com 2, 40% com 3
            $numDistractors = ($roll <= 60) ? 2 : 3;
        } else {
            // 6+ lacunas: 2-3 distratores
            $numDistractors = ($roll <= 50) ? 2 : 3;
        }

        if ($numDistractors === 0) {
            return [];
        }

        $correctSet = array_map('mb_strtolower', $correctWords);

        $available = array_filter(
            LegalVocabulary::WORDS,
            fn($w) => !in_array(mb_strtolower($w), $correctSet, true) && mb_strlen($w) >= 3
        );

        $available = array_values($available);

        if (empty($available)) {
            return [];
        }

        // Embaralha e pega os primeiros
        shuffle($available);

        $distractors = [];
        for ($i = 0; $i < $numDistractors && $i < count($available); $i++) {
            $distractors[] = ['word' => $available[$i]];
        }

        return $distractors;
    }
}
