<?php

namespace App\Services;

use App\Models\Legislation;
use App\Models\LegislationSegment;
use App\Models\SegmentLacuna;
use Illuminate\Support\Facades\DB;

class BlockDetectionService
{
    public function __construct(
        private LacunaAutoSelectionService $lacunaService,
    ) {}

    /**
     * Auto-cria blocos com lacunas para todos os segmentos de uma legislação.
     */
    public function autoCreateAllBlocks(Legislation $legislation): void
    {
        DB::transaction(function () use ($legislation) {
            $segments = $legislation->segments()
                ->where('is_block', false)
                ->where('segment_type', '!=', 'titulo')
                ->where('segment_type', '!=', 'capitulo')
                ->orderBy('position')
                ->get();

            foreach ($segments as $segment) {
                $this->createBlock($segment);
            }
        });
    }

    /**
     * Transforma um segmento em bloco ativo com lacunas auto-selecionadas.
     */
    public function createBlock(LegislationSegment $segment): void
    {
        $segment->update(['is_block' => true]);

        // Gera lacunas automaticamente
        $result = $this->lacunaService->selectLacunas($segment->original_text);

        // Cria lacunas corretas
        foreach ($result['lacunas'] as $lacuna) {
            SegmentLacuna::create([
                'legislation_segment_id' => $segment->id,
                'word' => $lacuna['word'],
                'word_position' => $lacuna['position'],
                'is_correct' => true,
                'gap_order' => $lacuna['gap_order'],
            ]);
        }

        // Cria distratores
        foreach ($result['distractors'] as $distractor) {
            SegmentLacuna::create([
                'legislation_segment_id' => $segment->id,
                'word' => $distractor['word'],
                'word_position' => 0,
                'is_correct' => false,
                'gap_order' => null,
            ]);
        }
    }

    /**
     * Remove um bloco: desmarca is_block e deleta lacunas.
     */
    public function removeBlock(LegislationSegment $segment): void
    {
        $segment->lacunas()->delete();
        $segment->update(['is_block' => false]);
    }
}
