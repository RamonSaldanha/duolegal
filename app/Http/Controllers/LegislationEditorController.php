<?php

namespace App\Http\Controllers;

use App\Models\Legislation;
use App\Models\LegislationSegment;
use App\Models\SegmentLacuna;
use App\Models\UserSegmentProgress;
use App\Services\BlockBoundaryService;
use App\Services\LacunaAutoSelectionService;
use App\Services\LegislationTextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LegislationEditorController extends Controller
{
    public function __construct(
        private LegislationTextService $textService,
        private BlockBoundaryService $boundaryService,
        private LacunaAutoSelectionService $lacunaService,
    ) {}

    /**
     * Lista todas as legislações do editor.
     */
    public function index()
    {
        $legislations = Legislation::with('legalReference')
            ->withCount('segments')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($l) => [
                'uuid' => $l->uuid,
                'title' => $l->title,
                'status' => $l->status,
                'source_url' => $l->source_url,
                'legal_reference' => $l->legalReference?->name,
                'segments_count' => $l->segments_count,
                'blocks_count' => $l->segments()->where('is_block', true)->count(),
                'created_at' => $l->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('LegislationEditor/Index', [
            'legislations' => $legislations,
        ]);
    }

    /**
     * Cria uma nova legislação (apenas título).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:500',
        ]);

        $legislation = Legislation::create([
            'title' => $validated['title'],
            'raw_text' => '',
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('editor.show', $legislation->uuid);
    }

    /**
     * Importa texto de uma URL para uma legislação existente.
     * Salva APENAS o raw_text — segmentos são criados progressivamente.
     */
    public function importText(Request $request, Legislation $legislation)
    {
        $validated = $request->validate([
            'source_url' => 'required|url|max:2048',
        ]);

        try {
            $rawText = $this->textService->fetchText($validated['source_url']);
        } catch (\Exception $e) {
            return back()->withErrors(['source_url' => 'Não foi possível acessar a URL: ' . $e->getMessage()]);
        }

        if (empty(trim($rawText))) {
            return back()->withErrors(['source_url' => 'Não foi possível extrair texto da URL.']);
        }

        DB::transaction(function () use ($legislation, $validated, $rawText) {
            // Remove segmentos antigos se houver
            $legislation->segments()->delete();

            $legislation->update([
                'source_url' => $validated['source_url'],
                'raw_text' => $rawText,
            ]);
        });

        return back();
    }

    /**
     * Exibe o editor de uma legislação.
     */
    public function show(Legislation $legislation)
    {
        $legislation->load([
            'segments' => fn($q) => $q->orderBy('position'),
            'segments.activeLacunas',
            'legalReference',
        ]);

        // Quantos alunos já concluíram cada bloco (para avisar antes de remover um bloco)
        $completedCounts = UserSegmentProgress::query()
            ->join('legislation_segments', 'legislation_segments.id', '=', 'user_segment_progress.legislation_segment_id')
            ->where('legislation_segments.legislation_id', $legislation->id)
            ->where('user_segment_progress.is_completed', true)
            ->selectRaw('user_segment_progress.legislation_segment_id, COUNT(*) as total')
            ->groupBy('user_segment_progress.legislation_segment_id')
            ->pluck('total', 'legislation_segment_id');

        $segments = $legislation->segments->map(
            fn($seg) => $this->formatSegment($seg, (int) ($completedCounts[$seg->id] ?? 0))
        );

        return Inertia::render('LegislationEditor/Editor', [
            'legislation' => [
                'uuid' => $legislation->uuid,
                'title' => $legislation->title,
                'source_url' => $legislation->source_url,
                'raw_text' => $legislation->raw_text ?? '',
                'status' => $legislation->status,
                'legal_reference' => $legislation->legalReference?->name,
            ],
            'segments' => $segments,
        ]);
    }

    /**
     * Cria um bloco a partir de char_start+char_end (auto-criar).
     */
    public function createBlockAtPosition(Request $request, Legislation $legislation)
    {
        $validated = $request->validate([
            'char_start' => 'required|integer|min:0',
            'char_end' => 'required|integer|min:1',
        ]);

        $rawText = $legislation->raw_text;

        if (empty($rawText)) {
            return back()->withErrors(['error' => 'Legislação sem texto importado.']);
        }

        $boundary = $this->boundaryFromRange($rawText, $validated['char_start'], $validated['char_end']);

        if ($boundary === null) {
            return back();
        }

        $familiarity = $this->rawTextFamiliarity($rawText);

        DB::transaction(function () use ($legislation, $boundary, $familiarity) {
            $maxPosition = $legislation->segments()->max('position') ?? 0;

            $this->createSegment($legislation, $boundary, $maxPosition + 1, $familiarity);
        });

        return back();
    }

    /**
     * Cria vários blocos de uma vez (JSON). Usado pela criação automática no editor:
     * devolve só os blocos novos, sem recarregar a legislação inteira a cada bloco.
     */
    public function storeBlocks(Request $request, Legislation $legislation)
    {
        $validated = $request->validate([
            'boundaries' => 'required|array|max:100',
            'boundaries.*.char_start' => 'required|integer|min:0',
            'boundaries.*.char_end' => 'required|integer|min:1',
        ]);

        $rawText = $legislation->raw_text;

        if (empty($rawText)) {
            return response()->json(['error' => 'Legislação sem texto importado.'], 422);
        }

        // Uma detecção por lote (já traz artigo e marcador), em vez de recalcular por bloco
        $detected = [];
        foreach ($this->boundaryService->detectAllBoundaries($rawText) as $boundary) {
            $detected[$boundary['char_start'].':'.$boundary['char_end']] = $boundary;
        }

        $existingRanges = $legislation->segments()
            ->select('char_start', 'char_end')
            ->get()
            ->map(fn($s) => ['char_start' => $s->char_start, 'char_end' => $s->char_end])
            ->toArray();

        $familiarity = $this->rawTextFamiliarity($rawText);

        $created = DB::transaction(function () use ($legislation, $validated, $detected, $rawText, $familiarity, &$existingRanges) {
            $position = $legislation->segments()->max('position') ?? 0;
            $created = [];

            foreach ($validated['boundaries'] as $requested) {
                $key = $requested['char_start'].':'.$requested['char_end'];
                $boundary = $detected[$key]
                    ?? $this->boundaryFromRange($rawText, $requested['char_start'], $requested['char_end']);

                if ($boundary === null || trim($boundary['original_text']) === '') {
                    continue;
                }

                foreach ($existingRanges as $range) {
                    if ($boundary['char_start'] < $range['char_end'] && $boundary['char_end'] > $range['char_start']) {
                        continue 2;
                    }
                }

                $created[] = $this->createSegment($legislation, $boundary, ++$position, $familiarity);
                $existingRanges[] = ['char_start' => $boundary['char_start'], 'char_end' => $boundary['char_end']];
            }

            return $created;
        });

        return response()->json([
            'segments' => collect($created)
                ->each(fn($segment) => $segment->load('activeLacunas'))
                ->map(fn($segment) => $this->formatSegment($segment))
                ->values(),
        ]);
    }

    /**
     * Auto-cria blocos para todos os dispositivos detectados.
     */
    public function autoCreate(Legislation $legislation)
    {
        $rawText = $legislation->raw_text;

        if (empty($rawText)) {
            return back()->withErrors(['error' => 'Legislação sem texto importado.']);
        }

        $boundaries = $this->boundaryService->detectAllBoundaries($rawText);

        // Filtra boundaries que já existem como segmentos
        $existingRanges = $legislation->segments()
            ->select('char_start', 'char_end')
            ->get()
            ->map(fn($s) => ['char_start' => $s->char_start, 'char_end' => $s->char_end])
            ->toArray();

        $familiarity = $this->rawTextFamiliarity($rawText);

        DB::transaction(function () use ($legislation, $boundaries, $existingRanges, $familiarity) {
            $maxPosition = $legislation->segments()->max('position') ?? 0;

            foreach ($boundaries as $boundary) {
                // Verifica sobreposição
                $overlaps = false;
                foreach ($existingRanges as $range) {
                    if ($boundary['char_start'] < $range['char_end'] && $boundary['char_end'] > $range['char_start']) {
                        $overlaps = true;
                        break;
                    }
                }

                if ($overlaps) {
                    continue;
                }

                $this->createSegment($legislation, $boundary, ++$maxPosition, $familiarity);

                // Adiciona ao existingRanges para evitar sobreposição dentro do batch
                $existingRanges[] = ['char_start' => $boundary['char_start'], 'char_end' => $boundary['char_end']];
            }
        });

        return back();
    }

    /**
     * Atualiza título/status de uma legislação.
     */
    public function update(Request $request, Legislation $legislation)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:500',
            'status' => 'sometimes|string|in:draft,published',
        ]);

        $legislation->update($validated);

        return back();
    }

    /**
     * Exclui uma legislação e todos seus segmentos/lacunas.
     */
    public function destroy(Legislation $legislation)
    {
        $legislation->delete();

        return redirect()->route('editor.index');
    }

    /**
     * Busca texto de uma URL (AJAX).
     */
    public function fetchText(Request $request)
    {
        $request->validate([
            'url' => 'required|url|max:2048',
        ]);

        try {
            $text = $this->textService->fetchText($request->url);

            return response()->json(['text' => $text]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Detecta limites de bloco sem criar segmentos.
     * Retorna JSON para criação progressiva no frontend.
     */
    public function detectBoundaries(Legislation $legislation)
    {
        $rawText = $legislation->raw_text;

        if (empty($rawText)) {
            return response()->json(['boundaries' => [], 'total' => 0]);
        }

        $boundaries = $this->boundaryService->detectAllBoundaries($rawText);

        $existingRanges = $legislation->segments()
            ->select('char_start', 'char_end')
            ->get()
            ->map(fn($s) => ['char_start' => $s->char_start, 'char_end' => $s->char_end])
            ->toArray();

        $filtered = array_values(array_filter($boundaries, function ($boundary) use ($existingRanges) {
            foreach ($existingRanges as $range) {
                if ($boundary['char_start'] < $range['char_end'] && $boundary['char_end'] > $range['char_start']) {
                    return false;
                }
            }

            return true;
        }));

        return response()->json([
            'boundaries' => array_map(fn($b) => [
                'char_start' => $b['char_start'],
                'char_end' => $b['char_end'],
                'segment_type' => $b['segment_type'],
            ], $filtered),
            'total' => count($filtered),
        ]);
    }

    /**
     * Remove um bloco (segmento) e suas lacunas.
     */
    public function removeBlock(LegislationSegment $legislationSegment)
    {
        $legislationSegment->lacunas()->delete();
        $legislationSegment->delete();

        return back();
    }

    /**
     * Liga/desliga uma palavra como lacuna em um segmento.
     */
    public function toggleLacuna(Request $request, LegislationSegment $legislationSegment)
    {
        $validated = $request->validate([
            'word' => 'required|string|max:255',
            'word_position' => 'required|integer|min:0',
        ]);

        // Verifica se já existe essa lacuna (ativa ou oculta)
        $existing = $legislationSegment->lacunas()
            ->where('word', $validated['word'])
            ->where('word_position', $validated['word_position'])
            ->where('is_correct', true)
            ->first();

        // Desligar oculta a lacuna em vez de apagá-la; ligar reativa a oculta
        if ($existing) {
            $existing->update(['is_active' => ! $existing->is_active]);
        } else {
            SegmentLacuna::create([
                'legislation_segment_id' => $legislationSegment->id,
                'word' => $validated['word'],
                'word_position' => $validated['word_position'],
                'is_correct' => true,
            ]);
        }

        $this->renumberActiveGaps($legislationSegment);

        return back();
    }

    /**
     * Adiciona um distrator a um segmento.
     */
    public function addDistractor(Request $request, LegislationSegment $legislationSegment)
    {
        $validated = $request->validate([
            'word' => 'required|string|max:255',
        ]);

        SegmentLacuna::create([
            'legislation_segment_id' => $legislationSegment->id,
            'word' => $validated['word'],
            'word_position' => 0,
            'is_correct' => false,
            'gap_order' => null,
        ]);

        return back();
    }

    /**
     * Tira uma lacuna ou distrator do jogo. A linha fica oculta (não é apagada).
     */
    public function removeLacuna(SegmentLacuna $segmentLacuna)
    {
        $segmentLacuna->update(['is_active' => false]);

        if ($segmentLacuna->is_correct) {
            $this->renumberActiveGaps($segmentLacuna->segment);
        }

        return back();
    }

    /**
     * Renumera gap_order das lacunas ativas na ordem de leitura. As ocultas
     * mantêm o número que tinham.
     */
    private function renumberActiveGaps(LegislationSegment $segment): void
    {
        $active = $segment->activeLacunas()
            ->where('is_correct', true)
            ->orderBy('word_position')
            ->get();

        foreach ($active as $i => $lacuna) {
            if ($lacuna->gap_order !== $i + 1) {
                $lacuna->update(['gap_order' => $i + 1]);
            }
        }
    }

    /**
     * Cria um bloco e escolhe suas lacunas.
     *
     * @param  array{char_start: int, char_end: int, original_text: string, segment_type: string, article_reference: ?string, structural_marker: ?string}  $boundary
     * @param  array<string, int>  $familiarity
     */
    private function createSegment(Legislation $legislation, array $boundary, int $position, array $familiarity): LegislationSegment
    {
        $segment = LegislationSegment::create([
            'legislation_id' => $legislation->id,
            'original_text' => $boundary['original_text'],
            'char_start' => $boundary['char_start'],
            'char_end' => $boundary['char_end'],
            'segment_type' => $boundary['segment_type'],
            'article_reference' => $boundary['article_reference'],
            'structural_marker' => $boundary['structural_marker'],
            'position' => $position,
            'is_block' => true,
        ]);

        $result = $this->lacunaService->selectLacunas(
            $segment->original_text,
            $familiarity,
            $segment->structural_marker,
        );

        foreach ($result['lacunas'] as $lacuna) {
            SegmentLacuna::create([
                'legislation_segment_id' => $segment->id,
                'word' => $lacuna['word'],
                'word_position' => $lacuna['position'],
                'is_correct' => true,
                'gap_order' => $lacuna['gap_order'],
            ]);
        }

        foreach ($result['distractors'] as $distractor) {
            SegmentLacuna::create([
                'legislation_segment_id' => $segment->id,
                'word' => $distractor['word'],
                'word_position' => 0,
                'is_correct' => false,
                'gap_order' => null,
            ]);
        }

        return $segment;
    }

    /**
     * Monta um bloco a partir de um trecho escolhido do texto bruto (fora da detecção automática).
     */
    private function boundaryFromRange(string $rawText, int $start, int $end): ?array
    {
        $end = min($end, mb_strlen($rawText));
        $blockText = mb_substr($rawText, $start, $end - $start);
        $cleanText = trim($this->textService->cleanLegalReferences(trim($blockText)));

        if ($cleanText === '') {
            return null;
        }

        $segmentType = $this->textService->identifyType($blockText);

        // Para artigos (caput), extrai do próprio texto; para sub-dispositivos, busca o artigo-pai
        $articleRef = $segmentType === 'caput'
            ? $this->textService->extractArticleReference($blockText)
            : $this->textService->findArticleReferenceAtPosition($rawText, $start);

        return [
            'char_start' => $start,
            'char_end' => $end,
            'original_text' => $cleanText,
            'segment_type' => $segmentType,
            'article_reference' => $articleRef,
            'structural_marker' => $this->textService->extractMarker(trim($blockText)),
        ];
    }

    /**
     * Familiaridade das palavras na lei (em quantas linhas do texto cada uma aparece),
     * usada para preferir lacunas que o aluno vê com frequência.
     *
     * @return array<string, int>
     */
    private function rawTextFamiliarity(string $rawText): array
    {
        return $this->lacunaService->familiarity(preg_split('/\n+/u', $rawText, -1, PREG_SPLIT_NO_EMPTY));
    }

    private function formatSegment(LegislationSegment $segment, int $completedCount = 0): array
    {
        return [
            'completed_count' => $completedCount,
            'uuid' => $segment->uuid,
            'original_text' => $segment->original_text,
            'char_start' => $segment->char_start,
            'char_end' => $segment->char_end,
            'segment_type' => $segment->segment_type,
            'article_reference' => $segment->article_reference,
            'structural_marker' => $segment->structural_marker,
            'position' => $segment->position,
            'is_block' => $segment->is_block,
            'lacunas' => $segment->activeLacunas->map(fn($lac) => [
                'uuid' => $lac->uuid,
                'word' => $lac->word,
                'word_position' => $lac->word_position,
                'is_correct' => $lac->is_correct,
                'gap_order' => $lac->gap_order,
            ])->values(),
        ];
    }
}
