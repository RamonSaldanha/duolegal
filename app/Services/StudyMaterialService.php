<?php

namespace App\Services;

use App\Http\Controllers\BetaPlayController;
use App\Models\Legislation;
use App\Models\StudyMaterial;
use App\Models\StudyQuestion;
use App\Models\User;
use App\Models\UserBetaCheckpoint;
use App\Models\UserSegmentProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Material de estudo (resumo + questões) gravado por agentes de IA.
 *
 * Cada material cobre um "trecho": intervalo contínuo de blocos de uma lei, do primeiro
 * bloco pendente até o fim do quadrado do mapa beta em que ele está (um material por
 * quadrado). Um bloco é pendente enquanto não está dentro de nenhum trecho (com material ou
 * pulado). A posição é medida por char_start, então um bloco criado depois no meio de um
 * trecho já pertence a ele.
 *
 * O quadrado é o mesmo para todos os usuários: as fases são cortadas de 10 em 10 blocos a
 * partir do início da lei e a troca de lei acontece sempre em múltiplos de 4 fases.
 */
class StudyMaterialService
{
    /** Blocos de um quadrado do mapa beta: o tamanho máximo de um trecho. */
    public const SQUARE_BLOCKS = BetaPlayController::BETA_BLOCKS_PER_PHASE * BetaPlayController::PHASES_PER_GROUP;

    public const MIN_QUESTIONS = 1;

    public const MAX_QUESTIONS = 6;

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
     * O próximo trecho da lei: do primeiro bloco pendente até o fim do quadrado dele (ou até
     * o último bloco pendente em sequência, se um trecho já gravado começa antes do fim do
     * quadrado). Vazio quando a lei não tem bloco pendente.
     *
     * @return array<int, int> ids em ordem
     */
    public function nextRange(array $layout): array
    {
        $run = $this->firstPendingRun($layout);

        if (! $run) {
            return [];
        }

        $position = array_search($run[0], array_column($layout, 'id'), true);
        $leftInSquare = self::SQUARE_BLOCKS - ($position % self::SQUARE_BLOCKS);

        return array_slice($run, 0, $leftInSquare);
    }

    /**
     * Confere o trecho pedido e devolve os ids dos blocos dele, em ordem. O trecho tem de ser
     * exatamente o próximo da lei (nextRange): um material, ou um pulo, por quadrado.
     *
     * @return array<int, int>
     *
     * @throws ValidationException
     */
    public function validateRange(Legislation $legislation, int $firstBlockId, int $lastBlockId): array
    {
        $range = $this->nextRange($this->layout($legislation));

        if (! $range) {
            throw ValidationException::withMessages([
                'legislation' => 'Esta lei não tem blocos pendentes: todos já estão em algum trecho.',
            ]);
        }

        if ($firstBlockId !== $range[0]) {
            throw ValidationException::withMessages([
                'first_block_id' => "O trecho deve começar no primeiro bloco pendente da lei (id {$range[0]}), sem deixar buraco. Recebido: {$firstBlockId}. Use GET /next para ver os blocos pendentes.",
            ]);
        }

        $last = end($range);

        if ($lastBlockId !== $last) {
            $size = count($range);

            throw ValidationException::withMessages([
                'last_block_id' => "O trecho deve terminar no bloco {$last}: cada material cobre o quadrado inteiro do mapa ({$size} blocos, do {$range[0]} ao {$last}), que são os blocos devolvidos por GET /next. Recebido: {$lastBlockId}. Blocos sem matéria ficam fora de block_ids, mas dentro do trecho.",
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
            'questions.min' => "O material precisa de {$min} a {$max} questões (o padrão é {$max}).",
            'questions.max' => "O material precisa de {$min} a {$max} questões (o padrão é {$max}).",
            'questions.required' => "O material precisa de {$min} a {$max} questões (o padrão é {$max}).",
            'title.required' => 'Informe o título do material (title).',
            'title.max' => 'O título (title) pode ter até 255 caracteres.',
            'summary.required' => 'Informe o resumo em markdown (summary).',
            'summary.max' => 'O resumo (summary) pode ter até 20000 caracteres.',
            'questions.*.board.required' => 'Toda questão precisa da banca (board): cebraspe, fgv, fcc ou vunesp.',
            'questions.*.type.required' => 'Toda questão precisa do tipo (type): true_false ou multiple_choice.',
            'questions.*.statement.required' => 'Toda questão precisa do enunciado (statement).',
            'questions.*.correct_answer.required' => 'Toda questão precisa do gabarito (correct_answer).',
            'questions.*.explanation.required' => 'Toda questão precisa do comentário do gabarito (explanation).',
            'questions.*.legal_basis.required' => 'Toda questão precisa do fundamento (legal_basis): o dispositivo que sustenta o gabarito.',
            'questions.*.legal_basis.max' => 'O fundamento (legal_basis) pode ter até 500 caracteres.',
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
     * Desafio de cada quadrado do mapa beta (o último passo dele): os materiais publicados e
     * atuais cujo último bloco cai no quadrado, na ordem da lei, e quantos a pessoa já
     * concluiu ali. Normalmente é um material por quadrado; material gravado antes dessa regra
     * pode dividir o quadrado com outro. Uma consulta para a trilha inteira.
     *
     * @param  array<int, array<int, int>>  $squares  ids dos blocos de cada quadrado, na ordem da trilha
     * @return array<int, array<string, mixed>> um por quadrado, com as mesmas chaves
     */
    public function squareChallenges(User $user, array $squares): array
    {
        $squareOf = [];
        $order = [];
        $anchors = [];
        foreach ($squares as $key => $blockIds) {
            foreach ($blockIds as $blockId) {
                $order[$blockId] = count($order);
                $squareOf[$blockId] = $key;
            }
            $anchors[$key] = $blockIds ? (int) end($blockIds) : null;
        }

        $materials = collect();
        foreach (array_chunk(array_keys($squareOf), 5000) as $chunk) {
            $materials = $materials->merge(
                StudyMaterial::where('status', StudyMaterial::STATUS_PUBLISHED)
                    ->whereIn('last_block_id', $chunk)
                    ->withCount([
                        'questions',
                        'questions as true_false_count' => fn ($query) => $query->where('type', StudyQuestion::TYPE_TRUE_FALSE),
                    ])
                    ->get()
            );
        }

        $stale = array_flip($this->staleIds($materials));
        $bySquare = $materials
            ->reject(fn (StudyMaterial $material) => isset($stale[$material->id]))
            ->sortBy(fn (StudyMaterial $material) => $order[$material->last_block_id])
            ->groupBy(fn (StudyMaterial $material) => $squareOf[$material->last_block_id]);

        $anchorIds = array_values(array_filter($anchors));
        $uuids = collect();
        $progress = collect();
        foreach (array_chunk($anchorIds, 5000) as $chunk) {
            $uuids = $uuids->union(DB::table('legislation_segments')->whereIn('id', $chunk)->pluck('uuid', 'id'));
            $progress = $progress->union(
                UserBetaCheckpoint::where('user_id', $user->id)->whereIn('legislation_segment_id', $chunk)->get()->keyBy('legislation_segment_id')
            );
        }

        $result = [];
        foreach ($squares as $key => $blockIds) {
            $own = $bySquare->get($key, collect());
            $ids = $own->pluck('id')->all();
            $anchor = $anchors[$key];
            $done = $anchor && $progress->has($anchor)
                ? count(array_intersect($ids, $progress->get($anchor)->doneIds()))
                : 0;

            $result[$key] = [
                'key' => $anchor ? $uuids->get($anchor) : null,
                'total' => count($ids),
                'completed' => $done,
                'questions' => (int) $own->sum('questions_count'),
                'true_false_questions' => (int) $own->sum('true_false_count'),
                // Só para uso interno; saem antes de ir para a tela
                'anchor_block_id' => $anchor,
                'material_ids' => $ids,
            ];
        }

        return $result;
    }

    /** O material aparece no mapa: publicado e com o texto da lei igual ao de quando foi gerado. */
    public function isPlayable(StudyMaterial $material): bool
    {
        return $material->status === StudyMaterial::STATUS_PUBLISHED && ! $this->isStale($material);
    }

    /** O usuário concluiu todos os blocos cobertos pelo material. */
    public function isUnlockedFor(User $user, StudyMaterial $material): bool
    {
        $covered = $material->coveredBlockIds();

        return $covered && ! array_diff($covered, $this->completedBlocks($user, $covered));
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

    /**
     * Dos blocos informados, os que o usuário já concluiu.
     *
     * @return array<int, int>
     */
    private function completedBlocks(User $user, array $blockIds): array
    {
        return UserSegmentProgress::where('user_id', $user->id)
            ->whereIn('legislation_segment_id', $blockIds)
            ->where('is_completed', true)
            ->pluck('legislation_segment_id')
            ->map(fn ($id) => (int) $id)
            ->all();
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
