<?php

namespace App\Services;

use App\Models\LegislationSegment;
use App\Models\SegmentLacuna;

/**
 * Regras de escolha das lacunas e palavras falsas, pensadas para blocos fáceis:
 * 1 lacuna em blocos pequenos, em geral 2 nos demais (textos maiores têm chance de
 * mais, ver GAP_ODDS), preferindo palavras que o aluno vê com frequência na própria
 * lei. Tudo é determinístico: o mesmo texto gera sempre as mesmas lacunas.
 */
class LacunaAutoSelectionService
{
    /** Blocos com menos palavras que isso (fora o marcador) recebem 1 lacuna. */
    public const SMALL_BLOCK_WORDS = 12;

    /**
     * Chance (%) de cada quantidade de lacunas conforme o tamanho do bloco (palavras fora
     * do marcador). A maioria fica com 2; textos maiores têm chance de 3, e só os bem
     * grandes têm uma chance pequena de 4 ou 5.
     */
    private const GAP_ODDS = [
        150 => [2 => 30, 3 => 45, 4 => 20, 5 => 5],
        80 => [2 => 45, 3 => 40, 4 => 15],
        40 => [2 => 70, 3 => 30],
        self::SMALL_BLOCK_WORDS => [2 => 100],
    ];

    /** A segunda lacuna precisa estar a mais de 3 palavras da primeira. */
    private const MIN_GAP_DISTANCE = 4;

    private const PUNCTUATION = '/[.,;:()\[\]"\'“”‘’]/u';

    /**
     * Seleciona lacunas para um bloco novo.
     *
     * @param  array<string, int>  $familiarity  palavra (minúscula, sem pontuação) => em quantos trechos da lei aparece
     * @return array{lacunas: array<int, array{word: string, position: int, gap_order: int}>, distractors: array<int, array{word: string}>}
     */
    public function selectLacunas(string $text, array $familiarity = [], ?string $marker = null): array
    {
        $words = $this->extractWords($text);
        $markerCount = $this->markerWordCount($marker, $words);
        $numGaps = $this->targetGapCount(count($words) - $markerCount, $this->gapSeed($text));

        $candidates = [];
        foreach ($words as $word) {
            if ($word['word_index'] < $markerCount) {
                continue;
            }
            $score = $this->easeScore($word['text'], $familiarity);
            if ($score !== null) {
                $candidates[] = ['score' => $score, 'position' => $word['word_index'], 'word' => $word['text']];
            }
        }

        $selected = $this->pickBest($candidates, $numGaps);

        $lacunas = [];
        foreach ($selected as $i => $sel) {
            $lacunas[] = ['word' => $sel['word'], 'position' => $sel['position'], 'gap_order' => $i + 1];
        }

        $distractors = $this->chooseDistractorWords(
            array_map(fn ($l) => $this->clean($l['word']), $lacunas),
            $text,
            $this->distractorCount(count($lacunas), $this->seed($text)),
            $this->seed($text),
        );

        return [
            'lacunas' => $lacunas,
            'distractors' => array_map(fn ($w) => ['word' => $w], $distractors),
        ];
    }

    /**
     * Planeja a facilitação de um bloco que já existe, sem apagar nada: escolhe
     * quais lacunas ficam ativas (preferindo as que já existem) e quais ficam ocultas.
     * Nunca deixa o bloco com mais lacunas do que ele tinha originalmente.
     *
     * Espera $segment->lacunas carregado (ativas e ocultas).
     *
     * @param  array<string, int>  $familiarity
     * @return array{active_ids: array<int, int>, inactive_ids: array<int, int>, create: array<int, array<string, mixed>>, gaps: int, distractors: int, words: array<int, string>}
     */
    public function planSimplification(LegislationSegment $segment, array $familiarity): array
    {
        $text = $segment->original_text;
        $words = $this->extractWords($text);
        $markerCount = $this->markerWordCount($segment->structural_marker, $words);
        $seed = $this->seed($text);

        $lacunas = $segment->lacunas->sortBy('id')->values();
        $corrects = $lacunas->where('is_correct', true);
        $originalCorrectCount = $corrects->where('auto_added', false)->count();
        $numGaps = min($this->targetGapCount(count($words) - $markerCount, $this->gapSeed($text)), $originalCorrectCount);

        // Candidatas: lacunas já existentes que apontam de fato para a palavra no texto
        $candidates = [];
        foreach ($corrects as $lacuna) {
            $position = (int) $lacuna->word_position;
            if ($position < $markerCount || ! isset($words[$position])) {
                continue;
            }
            if ($this->clean($words[$position]['text']) !== $this->clean($lacuna->word)) {
                continue;
            }
            $score = $this->easeScore($words[$position]['text'], $familiarity);
            if ($score !== null) {
                $candidates[] = ['score' => $score, 'position' => $position, 'word' => $lacuna->word, 'id' => $lacuna->id];
            }
        }

        $selected = $this->pickBest($candidates, $numGaps);

        // Faltou lacuna aproveitável: escolhe palavras novas do próprio texto
        $create = [];
        if (count($selected) < $numGaps) {
            $taken = array_column($selected, 'position');
            $fresh = [];
            foreach ($words as $word) {
                if ($word['word_index'] < $markerCount || in_array($word['word_index'], $taken, true)) {
                    continue;
                }
                $score = $this->easeScore($word['text'], $familiarity);
                if ($score !== null) {
                    $fresh[] = ['score' => $score, 'position' => $word['word_index'], 'word' => $word['text']];
                }
            }

            $nextOrder = (int) $corrects->max('gap_order');
            foreach ($this->pickBest($fresh, $numGaps - count($selected), $selected) as $pick) {
                $existing = $corrects->first(fn ($l) => (int) $l->word_position === $pick['position']
                    && $this->clean($l->word) === $this->clean($pick['word']));
                if ($existing) {
                    $pick['id'] = $existing->id;
                } else {
                    $create[] = [
                        'word' => $pick['word'],
                        'word_position' => $pick['position'],
                        'is_correct' => true,
                        'gap_order' => ++$nextOrder,
                    ];
                }
                $selected[] = $pick;
            }
        }

        $activeIds = array_values(array_filter(array_column($selected, 'id')));

        // Palavras falsas: reaproveita as existentes antes de criar novas
        $distractorRows = $lacunas->where('is_correct', false)
            ->sortBy(fn ($l) => [$l->auto_added ? 1 : 0, $l->id])
            ->values();
        $chosenWords = $this->chooseDistractorWords(
            array_map(fn ($s) => $this->clean($s['word']), $selected),
            $text,
            $this->distractorCount(count($selected), $seed),
            $seed,
            $distractorRows->pluck('word')->all(),
        );

        $usedDistractorIds = [];
        foreach ($chosenWords as $word) {
            $row = $distractorRows->first(fn ($l) => $l->word === $word && ! in_array($l->id, $usedDistractorIds, true));
            if ($row) {
                $usedDistractorIds[] = $row->id;
            } else {
                $create[] = ['word' => $word, 'word_position' => 0, 'is_correct' => false, 'gap_order' => null];
            }
        }

        $activeIds = array_merge($activeIds, $usedDistractorIds);
        $inactiveIds = $lacunas->pluck('id')->reject(fn ($id) => in_array($id, $activeIds, true))->values()->all();

        usort($selected, fn ($a, $b) => $a['position'] - $b['position']);

        return [
            'active_ids' => $activeIds,
            'inactive_ids' => $inactiveIds,
            'create' => $create,
            'gaps' => count($selected),
            'distractors' => count($chosenWords),
            'words' => array_map(fn ($s) => $this->clean($s['word']), $selected),
        ];
    }

    /**
     * Aplica um plano de planSimplification. Só liga/desliga lacunas e cria as que faltam.
     */
    public function applySimplification(LegislationSegment $segment, array $plan): void
    {
        if (! empty($plan['active_ids'])) {
            SegmentLacuna::whereIn('id', $plan['active_ids'])->update(['is_active' => true]);
        }
        if (! empty($plan['inactive_ids'])) {
            SegmentLacuna::whereIn('id', $plan['inactive_ids'])->update(['is_active' => false]);
        }
        foreach ($plan['create'] as $row) {
            SegmentLacuna::create($row + [
                'legislation_segment_id' => $segment->id,
                'is_active' => true,
                'auto_added' => true,
            ]);
        }
    }

    /**
     * Em quantos trechos cada palavra aparece. Trechos = blocos da lei, ou linhas
     * do texto bruto quando a lei ainda não tem blocos.
     *
     * @param  iterable<string>  $texts
     * @return array<string, int>
     */
    public function familiarity(iterable $texts): array
    {
        $counts = [];
        foreach ($texts as $text) {
            $seen = [];
            foreach (preg_split('/\s+/u', (string) $text, -1, PREG_SPLIT_NO_EMPTY) as $word) {
                $seen[$this->clean($word)] = true;
            }
            foreach (array_keys($seen) as $word) {
                $counts[$word] = ($counts[$word] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * Quantas lacunas o bloco deve ter, sorteado pelas chances de GAP_ODDS de forma fixa:
     * o mesmo texto dá sempre o mesmo número.
     */
    public function targetGapCount(int $contentWords, int $seed = 0): int
    {
        if ($contentWords <= 0) {
            return 0;
        }
        if ($contentWords < self::SMALL_BLOCK_WORDS) {
            return 1;
        }

        $roll = $seed % 100;
        foreach (self::GAP_ODDS as $minWords => $odds) {
            if ($contentWords < $minWords) {
                continue;
            }
            $cumulative = 0;
            foreach ($odds as $gaps => $chance) {
                $cumulative += $chance;
                if ($roll < $cumulative) {
                    return $gaps;
                }
            }
        }

        return 2;
    }

    /**
     * Bloco de 1 lacuna leva 1 palavra falsa. Blocos com mais lacunas levam 0, 1 ou 2,
     * decidido pelo próprio texto (~35% / 45% / 20%), sem sorteio.
     */
    public function distractorCount(int $gapCount, int $seed): int
    {
        if ($gapCount <= 0) {
            return 0;
        }
        if ($gapCount === 1) {
            return 1;
        }

        $roll = $seed % 100;

        return $roll < 35 ? 0 : ($roll < 80 ? 1 : 2);
    }

    /**
     * Pontua o quão boa (fácil e útil) uma palavra é como lacuna. null = não pode ser lacuna.
     *
     * @param  array<string, int>  $familiarity
     */
    public function easeScore(string $word, array $familiarity): ?int
    {
        $raw = preg_replace(self::PUNCTUATION, '', $word);
        $clean = mb_strtolower($raw);
        $length = mb_strlen($clean);

        if ($length < 3
            || in_array($clean, LegalVocabulary::STOP_WORDS, true)
            || preg_match('/[\d§]/u', $clean)
            || ! preg_match('/^\p{L}[\p{L}\-]*$/u', $clean)
        ) {
            return null;
        }

        $isKeyTerm = in_array($clean, LegalVocabulary::HIGH_VALUE_NOUNS, true);
        $score = $isKeyTerm ? 3 : 0;

        // Palavras que o aluno vê o tempo todo na lei são as mais fáceis de lembrar
        if (! empty($familiarity)) {
            $seen = $familiarity[$clean] ?? 0;
            $score += match (true) {
                $seen >= 50 => 3,
                $seen >= 10 => 2,
                $seen >= 3 => 1,
                $seen <= 1 => -2,
                default => 0,
            };
        }

        if (preg_match('/(ção|dade|mento|ncia)$/u', $clean)) {
            $score += 1;
        }

        if ($length >= 5 && $length <= 10) {
            $score += 1;
        } elseif ($length >= 13) {
            $score -= 2;
        }

        if (mb_strtoupper($raw) === $raw) {
            // Títulos e cabeçalhos em caixa alta
            $score -= 3;
        } elseif (! $isKeyTerm && mb_substr($raw, 0, 1) !== mb_strtolower(mb_substr($raw, 0, 1))) {
            // Nomes próprios
            $score -= 1;
        }

        return $score;
    }

    /**
     * Ordena por pontuação (empate: a que vem primeiro no texto) e pega as melhores,
     * sem repetir palavra e mantendo distância entre as lacunas quando possível.
     *
     * @param  array<int, array{score: int, position: int, word: string}>  $candidates
     * @param  array<int, array{position: int, word: string}>  $alreadySelected
     * @return array<int, array<string, mixed>>
     */
    private function pickBest(array $candidates, int $count, array $alreadySelected = []): array
    {
        usort($candidates, fn ($a, $b) => [$b['score'], $a['position']] <=> [$a['score'], $b['position']]);

        $picked = [];
        $usedWords = array_map(fn ($s) => $this->clean($s['word']), $alreadySelected);
        $usedPositions = array_column($alreadySelected, 'position');

        foreach ([true, false] as $requireDistance) {
            foreach ($candidates as $candidate) {
                if (count($picked) >= $count) {
                    return $picked;
                }
                if (in_array($this->clean($candidate['word']), $usedWords, true)) {
                    continue;
                }
                if ($requireDistance) {
                    foreach ($usedPositions as $position) {
                        if (abs($position - $candidate['position']) < self::MIN_GAP_DISTANCE) {
                            continue 2;
                        }
                    }
                }
                $picked[] = $candidate;
                $usedWords[] = $this->clean($candidate['word']);
                $usedPositions[] = $candidate['position'];
            }
        }

        return $picked;
    }

    /**
     * Palavras falsas que não aparecem no texto do bloco (para não haver duas
     * respostas plausíveis). Usa primeiro as preferidas (as já cadastradas).
     *
     * @param  array<int, string>  $correctWords  minúsculas, sem pontuação
     * @param  array<int, string>  $preferred
     * @return array<int, string>
     */
    private function chooseDistractorWords(array $correctWords, string $text, int $count, int $seed, array $preferred = []): array
    {
        if ($count <= 0) {
            return [];
        }

        $inText = array_flip(array_map(fn ($w) => $this->clean($w['text']), $this->extractWords($text)));
        $fits = fn (string $w) => ! isset($inText[$this->clean($w)]) && ! in_array($this->clean($w), $correctWords, true);

        $chosen = [];
        foreach ($preferred as $word) {
            if (count($chosen) >= $count) {
                return $chosen;
            }
            if ($fits($word) && ! in_array($word, $chosen, true)) {
                $chosen[] = $word;
            }
        }

        $vocabulary = array_values(array_filter(LegalVocabulary::WORDS, $fits));
        $total = count($vocabulary);
        for ($i = 0; $i < $total && count($chosen) < $count; $i++) {
            $word = $vocabulary[($seed + $i * 7) % $total];
            if (! in_array($word, $chosen, true)) {
                $chosen[] = $word;
            }
        }

        return $chosen;
    }

    /**
     * Quantas palavras do início do texto pertencem ao marcador ("Art. 5º", "§ 1º", "IV -").
     * O jogo não desenha essas palavras como texto, então elas nunca podem virar lacuna.
     *
     * @param  array<int, array{text: string, word_index: int}>  $words
     */
    private function markerWordCount(?string $marker, array $words): int
    {
        if (! $marker) {
            return 0;
        }

        $count = count(preg_split('/\s+/u', trim($marker), -1, PREG_SPLIT_NO_EMPTY));
        if (isset($words[$count]) && preg_match('/^[-–—]$/u', $words[$count]['text'])) {
            $count++;
        }

        return $count;
    }

    /**
     * Palavras (tokens sem espaço) na mesma contagem usada pelo jogo para word_position.
     *
     * @return array<int, array{text: string, word_index: int}>
     */
    private function extractWords(string $text): array
    {
        $words = [];
        foreach (preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $i => $token) {
            $words[] = ['text' => $token, 'word_index' => $i];
        }

        return $words;
    }

    private function clean(string $word): string
    {
        return mb_strtolower(preg_replace(self::PUNCTUATION, '', $word));
    }

    private function seed(string $text): int
    {
        return crc32($text);
    }

    /** Semente separada para a quantidade de lacunas, independente da de palavras falsas. */
    private function gapSeed(string $text): int
    {
        return crc32('lacunas|'.$text);
    }
}
