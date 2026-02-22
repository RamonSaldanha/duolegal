<?php

namespace App\Http\Controllers;

use App\Models\Legislation;
use App\Models\LegislationSegment;
use App\Models\SegmentLacuna;
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

        return redirect()->route('beta.editor.show', $legislation->uuid);
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
            'segments.lacunas',
            'legalReference',
        ]);

        $segments = $legislation->segments->map(fn($seg) => [
            'uuid' => $seg->uuid,
            'original_text' => $seg->original_text,
            'char_start' => $seg->char_start,
            'char_end' => $seg->char_end,
            'segment_type' => $seg->segment_type,
            'article_reference' => $seg->article_reference,
            'structural_marker' => $seg->structural_marker,
            'position' => $seg->position,
            'is_block' => $seg->is_block,
            'lacunas' => $seg->lacunas->map(fn($lac) => [
                'uuid' => $lac->uuid,
                'word' => $lac->word,
                'word_position' => $lac->word_position,
                'is_correct' => $lac->is_correct,
                'gap_order' => $lac->gap_order,
            ])->values(),
        ]);

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

        $start = $validated['char_start'];
        $end = min($validated['char_end'], mb_strlen($rawText));
        $blockText = mb_substr($rawText, $start, $end - $start);
        $cleanText = $this->textService->cleanLegalReferences(trim($blockText));

        if (empty(trim($cleanText))) {
            return back();
        }

        $segmentType = $this->textService->identifyType($blockText);

        // Para artigos (caput), extrai do próprio texto; para sub-dispositivos, busca o artigo-pai
        $articleRef = $segmentType === 'caput'
            ? $this->textService->extractArticleReference($blockText)
            : $this->textService->findArticleReferenceAtPosition($rawText, $start);

        $boundary = [
            'char_start' => $start,
            'char_end' => $end,
            'original_text' => $cleanText,
            'segment_type' => $segmentType,
            'article_reference' => $articleRef,
            'structural_marker' => $this->textService->extractMarker(trim($blockText)),
        ];

        DB::transaction(function () use ($legislation, $boundary) {
            $maxPosition = $legislation->segments()->max('position') ?? 0;

            $segment = LegislationSegment::create([
                'legislation_id' => $legislation->id,
                'original_text' => $boundary['original_text'],
                'char_start' => $boundary['char_start'],
                'char_end' => $boundary['char_end'],
                'segment_type' => $boundary['segment_type'],
                'article_reference' => $boundary['article_reference'],
                'structural_marker' => $boundary['structural_marker'],
                'position' => $maxPosition + 1,
                'is_block' => true,
            ]);

            // Auto-seleciona lacunas
            $result = $this->lacunaService->selectLacunas($segment->original_text);

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
        });

        return back();
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

        DB::transaction(function () use ($legislation, $boundaries, $existingRanges) {
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

                $maxPosition++;

                $segment = LegislationSegment::create([
                    'legislation_id' => $legislation->id,
                    'original_text' => $boundary['original_text'],
                    'char_start' => $boundary['char_start'],
                    'char_end' => $boundary['char_end'],
                    'segment_type' => $boundary['segment_type'],
                    'article_reference' => $boundary['article_reference'],
                    'structural_marker' => $boundary['structural_marker'],
                    'position' => $maxPosition,
                    'is_block' => true,
                ]);

                // Auto-seleciona lacunas
                $result = $this->lacunaService->selectLacunas($segment->original_text);

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

        return redirect()->route('beta.editor.index');
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

        // Verifica se já existe essa lacuna
        $existing = $legislationSegment->lacunas()
            ->where('word', $validated['word'])
            ->where('word_position', $validated['word_position'])
            ->where('is_correct', true)
            ->first();

        if ($existing) {
            $existing->delete();

            // Reordena gap_order
            $remaining = $legislationSegment->lacunas()
                ->where('is_correct', true)
                ->orderBy('word_position')
                ->get();

            foreach ($remaining as $i => $lac) {
                $lac->update(['gap_order' => $i + 1]);
            }
        } else {
            $maxOrder = $legislationSegment->lacunas()
                ->where('is_correct', true)
                ->max('gap_order') ?? 0;

            SegmentLacuna::create([
                'legislation_segment_id' => $legislationSegment->id,
                'word' => $validated['word'],
                'word_position' => $validated['word_position'],
                'is_correct' => true,
                'gap_order' => $maxOrder + 1,
            ]);
        }

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
     * Remove uma lacuna ou distrator.
     */
    public function removeLacuna(SegmentLacuna $segmentLacuna)
    {
        $segment = $segmentLacuna->segment;
        $wasCorrect = $segmentLacuna->is_correct;

        $segmentLacuna->delete();

        // Reordena gap_order se era uma lacuna correta
        if ($wasCorrect) {
            $remaining = $segment->lacunas()
                ->where('is_correct', true)
                ->orderBy('word_position')
                ->get();

            foreach ($remaining as $i => $lac) {
                $lac->update(['gap_order' => $i + 1]);
            }
        }

        return back();
    }
}
