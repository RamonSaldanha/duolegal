<?php

namespace App\Http\Controllers;

use App\Models\LawArticle;
use App\Models\LegalReference;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class PlayController extends Controller
{
    /**
     * Número de artigos por fase
     */
    const ARTICLES_PER_PHASE = 5;

    /**
     * Exibir o mapa de fases do jogo
     */
    public function map()
    {
        // Buscar referências legais com seus artigos
        $legalReferences = LegalReference::with(['articles' => function($query) {
            $query->orderBy('position', 'asc')->where('is_active', true);
        }])->get();
        
        // Preparar dados das fases
        $phases = [];
        $phaseCount = 0;
        
        foreach ($legalReferences as $reference) {
            // Agrupar artigos em grupos de ARTICLES_PER_PHASE
            $chunks = $reference->articles->chunk(self::ARTICLES_PER_PHASE);
            
            foreach ($chunks as $index => $articleChunk) {
                $phaseCount++;
                $phases[] = [
                    'id' => $phaseCount,
                    'title' => 'Fase ' . $phaseCount,
                    'reference_name' => $reference->name,
                    'reference_uuid' => $reference->uuid,
                    'article_count' => $articleChunk->count(),
                    'difficulty' => $this->calculateAverageDifficulty($articleChunk),
                    'first_article' => $articleChunk->first()->uuid ?? null,
                    'phase_number' => $index + 1,
                ];
            }
        }
        
        return Inertia::render('Play/Map', [
            'phases' => $phases
        ]);
    }
    
    /**
     * Visualizar os detalhes de uma fase
     */
    public function phase($referenceUuid, $phaseNumber)
    {
        $reference = LegalReference::where('uuid', $referenceUuid)->firstOrFail();
        
        // Obter artigos da fase específica COM suas opções
        $articles = $reference->articles()
            ->with('options')  // Adicionamos o eager loading das opções aqui
            ->orderBy('position', 'asc')
            ->where('is_active', true)
            ->get();
        // Obter grupo específico de artigos para esta fase
        $phaseArticles = $articles->chunk(self::ARTICLES_PER_PHASE)[$phaseNumber - 1] ?? collect([]);
        \Log::info('Phase Articles:', ['phaseNumber' => $phaseNumber, 'articles' => $phaseArticles]);
        
        if ($phaseArticles->isEmpty()) {
            abort(404);
        }
        
        return Inertia::render('Play/Phase', [
            'phase' => [
                'title' => 'Fase ' . $phaseNumber . ': ' . $reference->name,
                'reference_name' => $reference->name,
                'phase_number' => $phaseNumber,
                'difficulty' => $this->calculateAverageDifficulty($phaseArticles),
            ],
            'articles' => $phaseArticles->map(function($article) {
                return [
                    'uuid' => $article->uuid,
                    'article_reference' => $article->article_reference,
                    'original_content' => $article->original_content,
                    'practice_content' => $article->practice_content,
                    // Adicionamos as opções ao resultado
                    'options' => $article->options->map(function($option) {
                        return [
                            'id' => $option->id,
                            'word' => $option->word,
                            'is_correct' => $option->is_correct,
                            'gap_order' => $option->gap_order,
                            'position' => $option->position,
                        ];
                    })->sortBy('position')->values()->all()
                ];
            })
        ]);
    }
    
    /**
     * Calcular dificuldade média de um conjunto de artigos
     */
    private function calculateAverageDifficulty($articles)
    {
        if ($articles->isEmpty()) {
            return 1;
        }
        
        $totalDifficulty = $articles->sum('difficulty_level');
        return round($totalDifficulty / $articles->count());
    }
}
