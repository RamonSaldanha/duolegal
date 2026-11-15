<?php

namespace App\Services;

use App\Http\Controllers\BetaPlayController;
use App\Models\Legislation;
use App\Models\StudyMaterial;
use App\Models\StudyQuestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Material de estudo (resumo + questões) gravado por agentes de IA.
 *
 * O agente escolhe um "trecho": intervalo contínuo de blocos de uma lei, do primeiro ao
 * último, sempre começando no primeiro bloco pendente. Um bloco é pendente enquanto não
 * está dentro de nenhum trecho (com material ou pulado). A posição é medida por char_start,
 * então um bloco criado depois no meio de um trecho já pertence a ele.
 */
class StudyMaterialService
{
    /** Tamanho do trecho de um material. O agente mira em TARGET_BLOCKS. */
    public const MIN_BLOCKS = 4;

    public const TARGET_BLOCKS = 10;

    public const MAX_BLOCKS = 20;

    /** Tamanho de um trecho pulado. */
    public const MAX_SKIP_BLOCKS = 40;

    /** Blocos pendentes devolvidos de cada vez ao agente. */
    public const NEXT_LIMIT = 30;

    public const MIN_QUESTIONS = 1;

    public const MAX_QUESTIONS = 4;

    /**
     * Blocos da lei em ordem, cada um com `id`, `char_start` e `pending`.
     *
     * @return array<int, array{id: int, char_start: int, pending: bool}>
     */
    public function layout(Legislation $legislation): array
    {
        $blocks = DB::table('legislation_segments')
            ->where('legislation_id', $legislation->id)
            ->where('is_block', true)
            ->orderBy('char_start')
            ->orderBy('id')
            ->get(['id', 'char_start']);

        $charStart = $blocks->pluck('char_start', 'id');

        $ranges = [];
        foreach (StudyMaterial::where('legislation_id', $legislation->id)->get() as $material) {
            $range = $this->rangeOf($material, $charStart);
            if ($range) {
                $ranges[] = $range;
            }
        }
        usort($ranges, fn ($a, $b) => $a[0] <=> $b[0]);

        $layout = [];
        $r = 0;
        foreach ($blocks as $block) {
            while ($r < count($ranges) && $ranges[$r][1] < $block->char_start) {
                $r++;
            }

            $layout[] = [
                'id' => (int) $block->id,
                'char_start' => (int) $block->char_start,
                'pending' => ! ($r < count($ranges) && $ranges[$r][0] <= $block->char_start),
            ];
        }

        return $layout;
    }

    /**
     * Primeira sequência contínua de blocos pendentes da lei (ids em ordem), inteira.
     *
     * @return array<int, int>
     */
    public function firstPendingRun(array $layout): array
    {
        $run = [];
        foreach ($layout as $block) {
            if ($block['pending']) {
                $run[] = $block['id'];
            } elseif ($run) {
                break;
            }
        }

        return $run;
    }

    public function pendingCount(array $layout): int
    {
        return count(array_filter($layout, fn ($block) => $block['pending']));
    }

    /**
     * Confere o trecho pedido e devolve os ids dos blocos dele, em ordem.
     *
     * @return array<int, int>
     *
     * @throws ValidationException
     */
    public function validateRange(Legislation $legislation, int $firstBlockId, int $lastBlockId, int $min, int $max): array
    {
        $layout = $this->layout($legislation);
        $run = $this->firstPendingRun($layout);

        if (! $run) {
            throw ValidationException::withMessages([
                'legislation' => 'Esta lei não tem blocos pendentes: todos já estão em algum trecho.',
            ]);
        }

        if ($firstBlockId !== $run[0]) {
            throw ValidationException::withMessages([
                'first_block_id' => "O trecho deve começar no primeiro bloco pendente da lei (id {$run[0]}), sem deixar buraco. Recebido: {$firstBlockId}. Use GET /next para ver os blocos pendentes.",
            ]);
        }

        $ids = array_column($layout, 'id');
        $firstIndex = array_search($firstBlockId, $ids, true);
        $lastIndex = array_search($lastBlockId, $ids, true);

        if ($lastIndex === false) {
            throw ValidationException::withMessages([
                'last_block_id' => "O bloco {$lastBlockId} não é um bloco desta lei.",
            ]);
        }

        if ($lastIndex < $firstIndex) {
            throw ValidationException::withMessages([
                'last_block_id' => 'last_block_id deve ser o próprio first_block_id ou um bloco depois dele na ordem da lei.',
            ]);
        }

        $range = array_slice($ids, $firstIndex, $lastIndex - $firstIndex + 1);
        $lastPending = end($run);

        if (count($range) > count($run)) {
            $taken = $range[count($run)];

            throw ValidationException::withMessages([
                'last_block_id' => "O trecho se sobrepõe a outro já gravado: o bloco {$taken} já pertence a um trecho. O último bloco que este trecho pode ter é {$lastPending}.",
            ]);
        }

        $size = count($range);

        if ($size > $max) {
            throw ValidationException::withMessages([
                'last_block_id' => "O trecho tem {$size} blocos; o permitido é de {$min} a {$max}.",
            ]);
        }

        // Menos que o mínimo só quando o trecho fecha toda a sequência pendente (fim da lei
        // ou buraco deixado por um trecho apagado); senão a lei nunca ficaria completa.
        if ($size < $min && $size !== count($run)) {
            $available = count($run);

            throw ValidationException::withMessages([
                'last_block_id' => "O trecho tem {$size} blocos; o permitido é de {$min} a {$max}. Há {$available} blocos pendentes em sequência a partir do bloco {$run[0]}.",
            ]);
        }

        return $range;
    }

    /**
     * Blocos cobertos pelo material: subconjunto não vazio do trecho.
     *
     * @param  array<int, int>  $range
     * @return array<int, int> na ordem da lei
     *
     * @throws ValidationException
     */
    public function validateCoveredBlocks(array $range, array $blockIds): array
    {
        $blockIds = array_values(array_unique(array_map('intval', $blockIds)));

        if (! $blockIds) {
            throw ValidationException::withMessages([
                'block_ids' => 'block_ids não pode ficar vazio: informe os blocos do trecho que o material cobre.',
            ]);
        }

        $outside = array_values(array_diff($blockIds, $range));
        if ($outside) {
            throw ValidationException::withMessages([
                'block_ids' => 'block_ids só pode ter blocos do trecho. Fora do trecho: '.implode(', ', $outside).'.',
            ]);
        }

        return array_values(array_intersect($range, $blockIds));
    }

    /**
     * Confere título, resumo e questões; devolve os dados limpos. Vale para o POST do
     * agente e para a edição no admin.
     *
     * @return array{title: string, summary: string, questions: array<int, array<string, mixed>>}
     *
     * @throws ValidationException
     */
    public function validateContent(array $data): array
    {
        $min = self::MIN_QUESTIONS;
        $max = self::MAX_QUESTIONS;

        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:20000'],
            'questions' => ['required', 'array', "min:{$min}", "max:{$max}"],
            'questions.*' => ['array'],
            'questions.*.board' => ['required', 'string'],
            'questions.*.type' => ['required', 'string'],
            'questions.*.statement' => ['required', 'string', 'max:5000'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.correct_answer' => ['required', 'string'],
            'questions.*.explanation' => ['required', 'string', 'max:5000'],
            'questions.*.legal_basis' => ['required', 'string', 'max:500'],
        ], [
            'questions.min' => "O material precisa de {$min} a {$max} questões (o padrão é 2).",
            'questions.max' => "O material precisa de {$min} a {$max} questões (o padrão é 2).",
            'questions.required' => "O material precisa de {$min} a {$max} questões (o padrão é 2).",
            'questions.*.explanation.required' => 'Toda questão precisa de explanation (comentário do gabarito).',
            'questions.*.legal_basis.required' => 'Toda questão precisa de legal_basis (dispositivo que fundamenta o gabarito).',
        ])->validate();

        $errors = [];
        $questions = [];

        foreach (array_values($validated['questions']) as $i => $question) {
            $board = Str::lower(trim($question['board']));
            $type = Str::lower(trim($question['type']));
            $options = $question['options'] ?? null;
            $answer = trim($question['correct_answer']);

            if (! in_array($board, StudyQuestion::BOARDS, true)) {
                $errors["questions.{$i}.board"] = "Banca inválida: \"{$board}\". Use cebraspe, fgv, fcc ou vunesp.";

                continue;
            }

            $expectedType = $board === 'cebraspe' ? StudyQuestion::TYPE_TRUE_FALSE : StudyQuestion::TYPE_MULTIPLE_CHOICE;
            if ($type !== $expectedType) {
                $errors["questions.{$i}.type"] = $board === 'cebraspe'
                    ? "Questão da banca cebraspe é de certo/errado: type deve ser true_false. Recebido: \"{$type}\"."
                    : "Questão da banca {$board} é de múltipla escolha: type deve ser multiple_choice. Recebido: \"{$type}\".";

                continue;
            }

            if ($type === StudyQuestion::TYPE_TRUE_FALSE) {
                $answer = Str::lower($answer);

                if (! empty($options)) {
                    $errors["questions.{$i}.options"] = 'Questão de certo/errado não tem alternativas: envie options como null.';
                } elseif (! in_array($answer, StudyQuestion::TRUE_FALSE_ANSWERS, true)) {
                    $errors["questions.{$i}.correct_answer"] = "Em certo/errado, correct_answer deve ser \"certo\" ou \"errado\". Recebido: \"{$answer}\".";
                }

                $options = null;
            } else {
                $answer = Str::upper($answer);
                $keys = is_array($options) ? array_map('strval', array_keys($options)) : [];

                if ($keys !== StudyQuestion::OPTION_KEYS) {
                    $errors["questions.{$i}.options"] = 'Questão de múltipla escolha precisa de exatamente 5 alternativas em options, com as chaves "A", "B", "C", "D" e "E", nessa ordem.';
                } elseif (collect($options)->contains(fn ($text) => ! is_string($text) || trim($text) === '')) {
                    $errors["questions.{$i}.options"] = 'Todas as 5 alternativas precisam de texto.';
                } elseif (! in_array($answer, StudyQuestion::OPTION_KEYS, true)) {
                    $errors["questions.{$i}.correct_answer"] = "Em múltipla escolha, correct_answer deve ser uma única letra de A a E. Recebido: \"{$answer}\".";
                } else {
                    $options = array_map('trim', $options);
                }
            }

            $questions[] = [
                'board' => $board,
                'type' => $type,
                'statement' => trim($question['statement']),
                'options' => $options,
                'correct_answer' => $answer,
                'explanation' => trim($question['explanation']),
                'legal_basis' => trim($question['legal_basis']),
            ];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'title' => trim($validated['title']),
            'summary' => $validated['summary'],
            'questions' => $questions,
        ];
    }

    /** Troca as questões do material pelas informadas (já validadas), na ordem. */
    public function replaceQuestions(StudyMaterial $material, array $questions): void
    {
        $material->questions()->delete();

        foreach ($questions as $i => $question) {
            $material->questions()->create($question + ['position' => $i + 1]);
        }
    }

    /** sha256 dos textos originais dos blocos cobertos, na ordem da lei. */
    public function contentHash(array $blockIds): string
    {
        $texts = DB::table('legislation_segments')
            ->whereIn('id', $blockIds)
            ->where('is_block', true)
            ->orderBy('char_start')
            ->orderBy('id')
            ->pluck('original_text')
            ->all();

        return $this->hashTexts($texts);
    }

    /**
     * Ids dos materiais desatualizados: o texto de algum bloco coberto mudou, um bloco
     * coberto deixou de existir ou o trecho perdeu o primeiro/último bloco.
     *
     * @param  Collection<int, StudyMaterial>  $materials
     * @return array<int, int>
     */
    public function staleIds(Collection $materials): array
    {
        $materials = $materials->reject(fn (StudyMaterial $material) => $material->isSkipped());

        $blockIds = $materials->flatMap(fn (StudyMaterial $material) => $material->coveredBlockIds())->unique()->values();

        $blocks = $blockIds->isEmpty() ? collect() : DB::table('legislation_segments')
            ->whereIn('id', $blockIds)
            ->where('is_block', true)
            ->get(['id', 'char_start', 'original_text'])
            ->keyBy('id');

        $stale = [];
        foreach ($materials as $material) {
            $covered = collect($material->coveredBlockIds())->map(fn ($id) => $blocks->get($id));

            if (
                $material->first_block_id === null
                || $material->last_block_id === null
                || $covered->isEmpty()
                || $covered->contains(null)
                || $this->hashTexts($covered->sortBy([['char_start', 'asc'], ['id', 'asc']])->pluck('original_text')->all()) !== $material->content_hash
            ) {
                $stale[] = $material->id;
            }
        }

        return $stale;
    }

    public function isStale(StudyMaterial $material): bool
    {
        return in_array($material->id, $this->staleIds(collect([$material])), true);
    }

    /**
     * Número (a partir de 1) do quadrado do mapa beta de cada material: o quadrado em que
     * cai o último bloco do trecho.
     *
     * @param  Collection<int, StudyMaterial>  $materials
     * @return array<int, int|null> id do material => número do quadrado
     */
    public function squareNumbers(array $layout, Collection $materials): array
    {
        $perSquare = BetaPlayController::BETA_BLOCKS_PER_PHASE * BetaPlayController::PHASES_PER_GROUP;
        $index = array_flip(array_column($layout, 'id'));

        $squares = [];
        foreach ($materials as $material) {
            $position = $index[$material->last_block_id] ?? null;
            $squares[$material->id] = $position === null ? null : intdiv($position, $perSquare) + 1;
        }

        return $squares;
    }

    /**
     * Resumo em markdown → HTML. HTML embutido é removido e links inseguros
     * (javascript:, data: etc.) são bloqueados.
     */
    public function renderSummary(?string $markdown): string
    {
        return Str::markdown($markdown ?? '', [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /** Intervalo [char_start inicial, final] que o trecho ocupa; nulo se não ocupa nada. */
    private function rangeOf(StudyMaterial $material, Collection $charStart): ?array
    {
        $start = $charStart->get($material->first_block_id);
        $end = $charStart->get($material->last_block_id);

        if ($start !== null && $end !== null) {
            return [(int) min($start, $end), (int) max($start, $end)];
        }

        // Perdeu um bloco de fronteira: segue ocupando o que sobrou dos blocos cobertos,
        // até o admin revisar ou apagar.
        $covered = collect($material->coveredBlockIds())
            ->map(fn ($id) => $charStart->get($id))
            ->filter(fn ($value) => $value !== null);

        return $covered->isEmpty() ? null : [(int) $covered->min(), (int) $covered->max()];
    }

    private function hashTexts(array $texts): string
    {
        return hash('sha256', implode("\n", $texts));
    }
}
