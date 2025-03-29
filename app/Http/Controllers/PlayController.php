<?php
// app\Http\Controllers\PlayController.php
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
    const ARTICLES_PER_PHASE = 5;

    /**
     * Intervalo para inserção de fases de revisão
     */
    const REVIEW_PHASE_INTERVAL = 3;

    /**
     * Exibir o mapa de fases do jogo
     */
    public function map()
    {
        $user = Auth::user();
        $userId = $user->id;
        
        if (!$user->hasLives()) {
            return redirect()->route('play.nolives');
        }
        
        // Buscar referências legais com seus artigos
        $legalReferences = LegalReference::with(['articles' => function($query) {
            $query->orderBy('position', 'asc')->where('is_active', true);
        }])->orderBy('id', 'asc')->get();
        
        // Preparar dados das fases
        $phases = [];
        $phaseCount = 0;
        $regularPhaseCount = 0;
        $isLawBlocked = false;
        
        // Verificar se existem artigos não completados (percentage < 100) para o usuário
        $hasIncompleteArticles = UserProgress::where('user_id', $userId)
            ->where('percentage', '<', 100)
            ->exists();
        
        $lastPhaseWasReview = false; // Controle para saber se a última fase adicionada foi revisão
        
        foreach ($legalReferences as $referenceIndex => $reference) {
            // Verifica se a lei anterior está completa
            if ($referenceIndex > 0) {
                $previousReference = $legalReferences[$referenceIndex - 1];
                $previousArticles = $previousReference->articles;
                
                // Verifica se todas as fases da lei anterior estão completas
                foreach ($previousArticles->chunk(self::ARTICLES_PER_PHASE) as $phaseArticles) {
                    $progress = $this->getPhaseProgress($userId, $phaseArticles);
                    if ($progress['completed'] < $progress['total']) {
                        $isLawBlocked = true;
                        break;
                    }
                }
            }
            // Agrupar artigos em grupos de ARTICLES_PER_PHASE
            $chunks = $reference->articles->chunk(self::ARTICLES_PER_PHASE);
            
            foreach ($chunks as $index => $articleChunk) {
                $progress = $this->getPhaseProgress($userId, $articleChunk);
                $phaseCount++;
                $regularPhaseCount++;
                
                // Verificar se a fase está completa - artigos sem status "pending"
                $pendingArticles = array_filter($progress['article_status'], function($status) {
                    return $status === 'pending';
                });
                $isPhaseComplete = empty($pendingArticles);
                
                // Se a última fase adicionada foi uma revisão e há artigos incompletos, bloquear esta fase
                $isBlockedAfterReview = $lastPhaseWasReview && $hasIncompleteArticles;
                $isBlocked = $isLawBlocked || $isBlockedAfterReview;
                
                $phases[] = [
                    'id' => $phaseCount,
                    'title' => 'Fase ' . $phaseCount,
                    'reference_name' => $reference->name,
                    'reference_uuid' => $reference->uuid,
                    'article_count' => $articleChunk->count(),
                    'difficulty' => $this->calculateAverageDifficulty($articleChunk),
                    'first_article' => $articleChunk->first()->uuid ?? null,
                    'phase_number' => $phaseCount,
                    'is_complete' => $isPhaseComplete,
                    'progress' => $progress,
                    'is_blocked' => $isBlocked, // Agora usa a variável que considera revisões anteriores
                    'is_review' => false,
                ];
                
                $lastPhaseWasReview = false; // Resetar o controle pois acabamos de adicionar uma fase regular
                
                // Inserir fase de revisão após cada REVIEW_PHASE_INTERVAL fases regulares
                if ($regularPhaseCount % self::REVIEW_PHASE_INTERVAL === 0) {
                    $phaseCount++;
                    $phases[] = [
                        'id' => $phaseCount,
                        'title' => 'Revisão ' . ceil($regularPhaseCount / self::REVIEW_PHASE_INTERVAL),
                        'reference_name' => $reference->name,
                        'reference_uuid' => $reference->uuid,
                        'article_count' => null,
                        'difficulty' => $this->calculateAverageDifficulty($articleChunk),
                        'first_article' => null,
                        'phase_number' => $phaseCount, // Alterado de 'review' para $phaseCount
                        'is_complete' => false,
                        'progress' => ['completed' => 0, 'total' => 1, 'percentage' => 0],
                        'is_blocked' => !$hasIncompleteArticles,
                        'is_review' => true,
                    ];
                    $lastPhaseWasReview = true; // Marcar que a última fase adicionada foi de revisão
                }
            }
        }
        
        return Inertia::render('Play/Map', [
            'phases' => $phases,
            'user' => [
                'lives' => $user->lives
            ]
        ]);
    }
    
    /**
     * Visualizar os detalhes de uma fase
     */
    public function phase($referenceUuid, $phaseNumber)
    {
        $user = Auth::user();
        
        // Verifica se o usuário tem vidas disponíveis
        if (!$user->hasLives()) {
            return redirect()->route('play.nolives');
        }

        // Verifica se as leis anteriores estão completas
        $legalReferences = LegalReference::with(['articles' => function($query) {
            $query->orderBy('position', 'asc')->where('is_active', true);
        }])->orderBy('id', 'asc')->get();
        
        $currentReferenceIndex = $legalReferences->search(function($reference) use ($referenceUuid) {
            return $reference->uuid === $referenceUuid;
        });

        // Verifica se todas as leis anteriores estão completas
        for ($i = 0; $i < $currentReferenceIndex; $i++) {
            $previousReference = $legalReferences[$i];
            $articles = $previousReference->articles;
            
            foreach ($articles->chunk(self::ARTICLES_PER_PHASE) as $phaseArticles) {
                $progress = $this->getPhaseProgress($user->id, $phaseArticles);
                if ($progress['completed'] < $progress['total']) {
                    return redirect()
                        ->route('play.map')
                        ->with('message', 'Complete todas as fases da ' . $previousReference->name . ' antes de avançar.');
                }
            }
        }
        
        $reference = LegalReference::where('uuid', $referenceUuid)->firstOrFail();

        // Obter todos os artigos desta referência para verificar o progresso
        $allArticles = $reference->articles()
            ->orderBy('position', 'asc')
            ->where('is_active', true)
            ->get();
        
        // Agrupar os artigos em fases
        $chunkedArticles = $allArticles->chunk(self::ARTICLES_PER_PHASE);
        
        // Encontrar a primeira fase que não foi totalmente tentada
        $firstIncompletePhase = 1;
        foreach ($chunkedArticles as $index => $phaseArticles) {
            $progress = $this->getPhaseProgress(Auth::id(), $phaseArticles);
            
            // Verificar se todos os artigos da fase foram pelo menos tentados
            // (artigos com status 'pending' não foram tentados)
            $pendingArticles = array_filter($progress['article_status'], function($status) {
                return $status === 'pending';
            });
            
            if (count($pendingArticles) > 0) {
                $firstIncompletePhase = $index + 1;
                break;
            }
        }
        
        // Se a fase solicitada vier depois da primeira fase não completa, redirecionar
        if ($phaseNumber > $firstIncompletePhase) {
            return redirect()
                ->route('play.map')
                ->with('message', 'Complete a fase atual antes de avançar para as próximas.');
        }
        
        // Se a fase solicitada estiver completa, redirecionar para a fase atual
        if ($phaseNumber < $firstIncompletePhase) {
            return redirect()
                ->route('play.map')
                ->with('message', 'Esta fase já foi concluída. Avance para a próxima fase.');
        }
        
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
                    'wrong_answers' => $progress->wrong_answers,
                    'revisions' => $progress->revisions,
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
                'reference_uuid' => $referenceUuid
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
        
        $user = Auth::user();
        
        // Calcula a porcentagem de acerto
        $percentage = ($validated['correct_answers'] / $validated['total_answers']) * 100;
        
        // Se o usuário acertou menos de 70%, perde uma vida
        if ($percentage < 70) {
            $user->decrementLife();
        }
        
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
        
        return response()->json([
            'success' => true,
            'progress' => [
                'percentage' => $progress->percentage,
                'is_completed' => $progress->is_completed,
                'best_score' => $progress->best_score,
                'attempts' => $progress->attempts,
                'wrong_answers' => $progress->wrong_answers,
                'revisions' => $progress->revisions,
            ],
            'user' => [
                'lives' => $user->lives
            ],
            'should_redirect' => $user->lives <= 0,
            'redirect_url' => $user->lives <= 0 ? route('play.nolives') : null
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
                'article_status' => array_fill(0, $articles->count(), 'pending') // Todos os artigos como pendentes
            ];
        }
        
        $articleIds = $articles->pluck('id');
        
        // Buscar o progresso de todos os artigos desta fase para o usuário
        $progressRecords = UserProgress::where('user_id', $userId)
            ->whereIn('law_article_id', $articleIds)
            ->get();
            
        $totalArticles = $articleIds->count();
        
        // Mapear o status de cada artigo (correct, incorrect, pending)
        $articleStatus = [];
        
        // Inicializar todos os artigos como pendentes
        foreach ($articles as $index => $article) {
            $articleStatus[$index] = 'pending';
        }
        
        // Atualizar o status com base nos registros de progresso
        foreach ($progressRecords as $progress) {
            // Encontrar o índice do artigo no array de artigos
            $index = $articles->search(function($article) use ($progress) {
                return $article->id === $progress->law_article_id;
            });
            
            if ($index !== false) {
                if ($progress->is_completed) {
                    $articleStatus[$index] = 'correct'; // Artigo completado com sucesso (>= 70%)
                } else {
                    $articleStatus[$index] = 'incorrect'; // Artigo tentado mas não completado (< 70%)
                }
            }
        }
        
        // Uma fase é considerada "completada" quando todos os artigos foram pelo menos tentados
        $pendingArticles = array_filter($articleStatus, function($status) {
            return $status === 'pending';
        });
        
        $completedCount = count($pendingArticles) === 0 ? $totalArticles : 0;
        $progressPercentage = $totalArticles > 0 ? round((($totalArticles - count($pendingArticles)) / $totalArticles) * 100, 2) : 0;
        
        return [
            'completed' => $completedCount,
            'total' => $totalArticles,
            'percentage' => $progressPercentage,
            'article_status' => array_values($articleStatus) // Converter para array indexado
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

    /**
     * Recompensa o usuário com uma vida após assistir o anúncio
     */
    public function rewardLife()
    {
        $user = Auth::user();
        $user->lives = min($user->lives + 1, 5); // Adiciona uma vida, máximo de 5
        $user->save();

        return response()->json([
            'success' => true,
            'user' => [
                'lives' => $user->lives
            ]
        ]);
    }

    /**
     * Exibir a fase de revisão para uma referência legal
     */
    public function review($referenceUuid, $phaseNumber)
    {
        // Remova o dd() e trate phaseNumber como inteiro
        $phaseNumber = (int)$phaseNumber;
        
        $user = Auth::user();
        
        // Verifica se o usuário tem vidas disponíveis
        if (!$user->hasLives()) {
            return redirect()->route('play.nolives');
        }
        
        // Buscar a referência legal
        $reference = LegalReference::where('uuid', $referenceUuid)->firstOrFail();
        
        // Buscar os IDs dos artigos da referência
        $articleIds = $reference->articles()
            ->where('is_active', true)
            ->pluck('id');
            
        // Buscar os artigos que o usuário já tentou mas não completou (percentage < 100)
        $incompleteArticleIds = UserProgress::where('user_id', $user->id)
            ->whereIn('law_article_id', $articleIds)
            ->where('percentage', '<', 100)
            ->pluck('law_article_id');
            
        // Se não há artigos incompletos, redirecionar para o mapa
        if ($incompleteArticleIds->isEmpty()) {
            return redirect()
                ->route('play.map')
                ->with('message', 'Não há artigos para revisar nesta referência legal. Todos os artigos estão completos!');
        }
        
        // Buscar os artigos incompletos COM suas opções
        $articlesWithProgress = LawArticle::with('options')
            ->whereIn('id', $incompleteArticleIds)
            ->where('is_active', true)
            ->orderBy('position', 'asc')
            ->get()
            ->map(function($article) use ($user) {
                $progress = UserProgress::where('user_id', $user->id)
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
                        'wrong_answers' => $progress->wrong_answers,
                        'revisions' => $progress->revisions,
                    ] : null,
                ];
            });
        
        return Inertia::render('Play/Phase', [
            'phase' => [
                'title' => 'Revisão: ' . $reference->name,
                'reference_name' => $reference->name,
                'phase_number' => $phaseNumber,
                'is_review' => true,
                'reference_uuid' => $referenceUuid
            ],
            'articles' => $articlesWithProgress
        ]);
    }
}
