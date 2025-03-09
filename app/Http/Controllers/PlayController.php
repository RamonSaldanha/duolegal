<?php

namespace App\Http\Controllers;

use App\Models\LawArticle;
use App\Models\LegalReference;
use App\Models\UserProgress;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class PlayController extends Controller
{
    /**
     * Número de artigos por fase
     */
    const ARTICLES_PER_PHASE = 3;

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
                    'progress' => $this->getPhaseProgress(Auth::id(), $articleChunk),
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
            ->with('options')
            ->orderBy('position', 'asc')
            ->where('is_active', true)
            ->get();
        
        // Verificar se o número da fase é válido
        $chunkedArticles = $articles->chunk(self::ARTICLES_PER_PHASE);
        
        if (!isset($chunkedArticles[$phaseNumber - 1]) || $chunkedArticles[$phaseNumber - 1]->isEmpty()) {
            // Se a fase solicitada não existe, redirecionar para o mapa de fases
            return redirect()->route('play.map')->with('message', 'Fase não encontrada');
        }
        
        $phaseArticles = $chunkedArticles[$phaseNumber - 1];
        
        // Obter o progresso dos artigos para o usuário autenticado
        $userId = Auth::id();
        $articlesWithProgress = $phaseArticles->map(function($article) use ($userId) {
            $progress = UserProgress::where('user_id', $userId)
                ->where('law_article_id', $article->id)
                ->first();
                
            return [
                'uuid' => $article->uuid,
                'article_reference' => $article->article_reference,
                'original_content' => $article->original_content,
                'practice_content' => $article->practice_content,
                'options' => $article->options->map(function($option) {
                    return [
                        'id' => $option->id,
                        'word' => $option->word,
                        'is_correct' => $option->is_correct,
                        'gap_order' => $option->gap_order,
                        'position' => $option->position,
                    ];
                })->sortBy('position')->values()->all(),
                'progress' => $progress ? [
                    'percentage' => $progress->percentage,
                    'is_completed' => $progress->is_completed,
                    'best_score' => $progress->best_score,
                    'attempts' => $progress->attempts,
                ] : null,
            ];
        });
        
        // Verificar se existe próxima fase para informar ao frontend
        $hasNextPhase = isset($chunkedArticles[$phaseNumber]);
        
        return Inertia::render('Play/Phase', [
            'phase' => [
                'title' => 'Fase ' . $phaseNumber . ': ' . $reference->name,
                'reference_name' => $reference->name,
                'phase_number' => (int)$phaseNumber,
                'difficulty' => $this->calculateAverageDifficulty($phaseArticles),
                'progress' => $this->getPhaseProgress(Auth::id(), $phaseArticles),
                'has_next_phase' => $hasNextPhase,
                'reference_uuid' => $referenceUuid, // Adicione esta linha
            ],
            'articles' => $articlesWithProgress
        ]);
    }
    
    /**
     * Registra o progresso do usuário em um artigo
     */
    public function saveProgress(Request $request)
    {
        $validated = $request->validate([
            'article_uuid' => 'required|string|exists:law_articles,uuid',
            'correct_answers' => 'required|integer|min:0',
            'total_answers' => 'required|integer|min:1',
        ]);
        
        // Obtém o ID do artigo a partir do UUID
        $article = LawArticle::where('uuid', $validated['article_uuid'])->firstOrFail();
        
        // Verifica e corrige possíveis valores inconsistentes
        $correctAnswers = min($validated['correct_answers'], $validated['total_answers']);
        $totalAnswers = $validated['total_answers'];
        
        // Registra o progresso
        $progress = UserProgress::updateProgress(
            Auth::id(),
            $article->id,
            $correctAnswers,
            $totalAnswers
        );
        
        Log::info('Progresso salvo:', [
            'article_uuid' => $validated['article_uuid'],
            'user_id' => Auth::id(),
            'correct_answers' => $correctAnswers,
            'total_answers' => $totalAnswers,
            'percentage' => $progress->percentage,
            'best_score' => $progress->best_score,
            'attempts' => $progress->attempts,
            'is_completed' => $progress->is_completed
        ]);
        
        return response()->json([
            'success' => true,
            'progress' => [
                'percentage' => $progress->percentage,
                'is_completed' => $progress->is_completed,
                'best_score' => $progress->best_score,
                'attempts' => $progress->attempts,
            ]
        ]);
    }
    
    /**
     * Obtém o progresso de uma fase para um usuário
     */
    private function getPhaseProgress($userId, $articles)
    {
        if (!$userId || $articles->isEmpty()) {
            return [
                'completed' => 0,
                'total' => $articles->count(),
                'percentage' => 0,
            ];
        }
        
        $articleIds = $articles->pluck('id');
        
        $completedCount = UserProgress::where('user_id', $userId)
            ->whereIn('law_article_id', $articleIds)
            ->where('is_completed', true)
            ->count();
            
        $totalArticles = $articleIds->count();
        $progressPercentage = $totalArticles > 0 ? round(($completedCount / $totalArticles) * 100, 2) : 0;
        
        return [
            'completed' => $completedCount,
            'total' => $totalArticles,
            'percentage' => $progressPercentage,
        ];
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
