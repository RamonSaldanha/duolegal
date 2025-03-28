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
use App\Models\User;

class PlayController extends Controller
{
    private function checkAuth()
    {
        $user = Auth::user();
        if (!$user) {
            redirect()->route('login');
        }
        return $user;
    }

    private function checkLives()
    {
        $user = $this->checkAuth();
        if ($user->lives <= 0) {
            return redirect()->route('play.nolives');
        }
        return $user;
    }

    /**
     * Número de artigos por fase
     */
    const ARTICLES_PER_PHASE = 5;

    /**
     * Número de fases antes de uma revisão
     */
    const PHASES_BEFORE_REVISION = 3;

    /**
     * Exibir o mapa de fases do jogo
     */
    public function map()
    {
        $user = Auth::user();
        $userId = $user->id;
        
        if ($user->lives <= 0) {
            return redirect()->route('play.nolives');
        }
        
        // Buscar referências legais com seus artigos
        $legalReferences = LegalReference::with(['articles' => function($query) {
            $query->orderBy('position', 'asc')->where('is_active', true);
        }])->orderBy('position', 'asc')->get();
        
        // Preparar dados das fases
        $phases = [];
        $phaseCount = 0;
        $isLawBlocked = false; // Controle para leis bloqueadas
        
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
            $referencePhaseCount = 0;
            
            foreach ($chunks as $index => $articleChunk) {
                $phaseCount++;
                $referencePhaseCount++;
                
                // Adicionar fase regular
                $progress = $this->getPhaseProgress($userId, $articleChunk);
                $isPhaseComplete = $progress['completed'] === $progress['total'];
                
                // Se for uma fase após revisão (index > 0 e index % 3 === 0)
                $isAfterRevision = $index > 0 && ($index % self::PHASES_BEFORE_REVISION === 0);
                
                $isBlocked = $isLawBlocked;
                
                // Fases após revisão só verificam a revisão anterior
                if ($isAfterRevision) {
                    $isBlocked = $isBlocked || !$this->isPreviousRevisionComplete($userId, $reference, $index);
                } 
                // Fases regulares verificam a fase anterior
                else if ($index > 0) {
                    $isBlocked = $isBlocked || !$this->isPreviousPhaseComplete($userId, $reference, $index);
                }
                
                $phases[] = [
                    'id' => $phaseCount,
                    'title' => 'Fase ' . $phaseCount,
                    'reference_name' => $reference->name,
                    'reference_uuid' => $reference->uuid,
                    'article_count' => $articleChunk->count(),
                    'difficulty' => $this->calculateAverageDifficulty($articleChunk),
                    'first_article' => $articleChunk->first()->uuid ?? null,
                    'phase_number' => $referencePhaseCount,
                    'is_complete' => $isPhaseComplete,
                    'progress' => $progress,
                    'is_blocked' => $isBlocked,
                    'type' => 'regular'
                ];

                // Verificar se deve adicionar uma fase de revisão
                if ($referencePhaseCount % self::PHASES_BEFORE_REVISION === 0) {
                    $phaseCount++;
                    $revisionNumber = $referencePhaseCount / self::PHASES_BEFORE_REVISION;
                    
                    // Chave para armazenar os IDs dos artigos de revisão na sessão
                    $revisionKey = "revision_{$reference->uuid}_{$revisionNumber}";
                    
                    // Seleciona artigos para revisão:
                    // - Artigos com menos de 70% OU não completados
                    // - Que ainda não foram revisados nesta rodada
                    // - Prioriza os com menor porcentagem e menos revisões
                    $revisionArticleIds = UserProgress::where('user_id', $userId)
                        ->whereIn('law_article_id', $reference->articles->pluck('id'))
                        ->where(function($query) {
                            $query->where('is_completed', false)
                                ->orWhere('percentage', '<', 70);
                        })
                        ->where(function($query) use ($revisionNumber) {
                            $query->where('revisions', '<', $revisionNumber)
                                  ->orWhereNull('last_revision_at');
                        })
                        ->orderByRaw('percentage ASC, revisions ASC, last_revision_at ASC')
                        ->limit(5)
                        ->pluck('law_article_id')
                        ->toArray();

                    // Garantir que não haja artigos duplicados
                    $revisionArticleIds = array_unique($revisionArticleIds);
                    
                    // Se não tivermos artigos suficientes, pegar mais artigos que precisam de revisão
                    if (count($revisionArticleIds) < 5) {
                        $additionalArticles = UserProgress::where('user_id', $userId)
                            ->whereIn('law_article_id', $reference->articles->pluck('id'))
                            ->whereNotIn('law_article_id', $revisionArticleIds)
                            ->where(function($query) {
                                $query->where('is_completed', false)
                                    ->orWhere('percentage', '<', 70);
                            })
                            ->orderByRaw('percentage ASC, revisions ASC, last_revision_at ASC')
                            ->limit(5 - count($revisionArticleIds))
                            ->pluck('law_article_id')
                            ->toArray();
                            
                        $revisionArticleIds = array_merge($revisionArticleIds, $additionalArticles);
                    }

                    // Armazenar/atualizar a sessão
                    session([$revisionKey => $revisionArticleIds]);

                    // Buscar os artigos de revisão com seus progressos
                    $articlesForRevision = UserProgress::where('user_id', $userId)
                        ->whereIn('law_article_id', $revisionArticleIds)
                        ->with('lawArticle')
                        ->get();

                    if ($articlesForRevision->isNotEmpty()) {
                        // Verifica se o usuário já completou esta fase de revisão
                        $revisionProgress = $this->getRevisionProgress($userId, $revisionArticleIds);
                        
                        // Para revisões, consideramos completa se todos os artigos foram respondidos (não necessariamente corretos)
                        $isRevisionComplete = $revisionProgress['completed'] === count($revisionArticleIds);
                        
                        $phases[] = [
                            'id' => $phaseCount,
                            'title' => 'Revisão ' . $revisionNumber,
                            'reference_name' => $reference->name,
                            'reference_uuid' => $reference->uuid,
                            'article_count' => count($revisionArticleIds),
                            'difficulty' => $this->calculateAverageDifficulty($articleChunk),
                            'first_article' => null,
                            'phase_number' => $revisionNumber + 0.5,
                            'is_complete' => $isRevisionComplete,
                            'progress' => $revisionProgress,
                            'is_blocked' => $isLawBlocked, // Revisões herdam apenas o bloqueio da lei
                            'type' => 'revision',
                            'revision_article_ids' => $revisionArticleIds
                        ];
                    }
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
        if ($user->lives <= 0) {
            return redirect()->route('play.nolives');
        }

        // Verifica se as leis anteriores estão completas
        $legalReferences = LegalReference::with(['articles' => function($query) {
            $query->orderBy('position', 'asc')->where('is_active', true);
        }])->orderBy('position', 'asc')->get();
        
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

        // Verifica se é uma fase de revisão (números decimais são fases de revisão)
        $isRevisionPhase = floor($phaseNumber) != $phaseNumber;

        if ($isRevisionPhase) {
            // Calcular o número da revisão
            $revisionNumber = ceil($phaseNumber * 2) - 2;
            
            // Chave para recuperar os IDs dos artigos de revisão da sessão
            $revisionKey = "revision_{$reference->uuid}_{$revisionNumber}";
            
            // Buscar IDs dos artigos para revisão
            $revisionArticleIds = session($revisionKey);
            
            if (!$revisionArticleIds) {
                // Se não existir na sessão, selecionar os artigos com menor pontuação
                // Seleciona artigos para revisão:
                // 1. Artigos não completados OU com porcentagem < 70
                // 2. Que ainda não foram revisados o suficiente (revisions < revisão atual)
                // 3. Prioriza artigos com menor porcentagem e menos revisões
                $revisionArticleIds = UserProgress::where('user_id', Auth::id())
                    ->whereIn('law_article_id', $reference->articles->pluck('id'))
                    ->where(function ($query) {
                        $query->where('is_completed', false)
                            ->orWhere('percentage', '<', 70);
                    })
                    ->where('revisions', '<', $revisionNumber)
                    ->orderByRaw('percentage ASC, revisions ASC')
                    ->limit(5)
                    ->pluck('law_article_id')
                    ->toArray();

                // Se não encontrou artigos suficientes, pega os que precisam de mais revisão
                if (count($revisionArticleIds) < 5) {
                    $additionalArticles = UserProgress::where('user_id', Auth::id())
                        ->whereIn('law_article_id', $reference->articles->pluck('id'))
                        ->where('revisions', '<', $revisionNumber + 1) // Permite mais uma revisão
                        ->whereNotIn('law_article_id', $revisionArticleIds)
                        ->orderBy('revisions', 'asc')
                        ->orderBy('percentage', 'asc')
                        ->limit(5 - count($revisionArticleIds))
                        ->pluck('law_article_id')
                        ->toArray();

                    $revisionArticleIds = array_merge($revisionArticleIds, $additionalArticles);
                }
                    
                // Armazenar na sessão
                session([$revisionKey => $revisionArticleIds]);
            }
            
            // Buscar os artigos de revisão com seus progressos
            $articlesForRevision = UserProgress::where('user_id', Auth::id())
                ->whereIn('law_article_id', $revisionArticleIds)
                ->with('lawArticle.options')
                ->get();

            if ($articlesForRevision->isEmpty()) {
                return redirect()->route('play.map')
                    ->with('message', 'Nenhum artigo disponível para revisão.');
            }

            // Verifica se a fase anterior foi completada
            $previousPhaseNumber = floor($phaseNumber);
            $chunkedArticles = $reference->articles()
                ->orderBy('position', 'asc')
                ->where('is_active', true)
                ->get()
                ->chunk(self::ARTICLES_PER_PHASE);

            if (isset($chunkedArticles[$previousPhaseNumber - 1])) {
                $previousPhaseArticles = $chunkedArticles[$previousPhaseNumber - 1];
                $previousPhaseProgress = $this->getPhaseProgress(Auth::id(), $previousPhaseArticles);
                
                if ($previousPhaseProgress['percentage'] < 100) {
                    return redirect()->route('play.map')
                        ->with('message', 'Complete a fase anterior antes de fazer a revisão.');
                }
            }

            // Preparar artigos para revisão
            $articlesWithProgress = $articlesForRevision->map(function($progress) {
                $article = $progress->lawArticle;
                return [
                    'uuid' => $article->uuid,
                    'article_reference' => $article->article_reference,
                    'original_content' => $article->original_content,
                    'practice_content' => $article->practice_content,
                    'options' => $article->options->map(function($option) {
                        return [
                            'id' => $option->id,
                            'word' => $option->word,
                            'gap_order' => $option->gap_order,
                            'position' => $option->position,
                        ];
                    })->sortBy('position')->values()->all(),
                    'progress' => [
                        'percentage' => $progress->percentage,
                        'is_completed' => $progress->is_completed,
                        'best_score' => $progress->best_score,
                        'attempts' => $progress->attempts,
                        'wrong_answers' => $progress->wrong_answers,
                        'revisions' => $progress->revisions,
                    ]
                ];
            });

            return Inertia::render('Play/Phase', [
                'phase' => [
                    'title' => 'Revisão ' . (ceil($phaseNumber * 2) - 2) . ': ' . $reference->name,
                    'reference_name' => $reference->name,
                    'phase_number' => (float)$phaseNumber,
                    'type' => 'revision',
                    'progress' => $this->getPhaseProgress(Auth::id(), $articlesForRevision->pluck('lawArticle')),
                    'reference_uuid' => $referenceUuid
                ],
                'articles' => $articlesWithProgress
            ]);
        }

        // Lógica para fases regulares
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
        $articlesWithProgress = $phaseArticles->map(function($article) {
            $progress = UserProgress::where('user_id', Auth::id())
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
        if ($percentage < 70 && $user->lives > 0) {
            User::where('id', $user->id)->update(['lives' => $user->lives - 1]);
            $user = Auth::user(); // Buscar usuário atualizado
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
     * Obtém o progresso de uma fase de revisão para um usuário
     */
    private function getRevisionProgress($userId, $articleIds)
    {
        if (!$userId || empty($articleIds)) {
            return [
                'completed' => 0,
                'total' => count($articleIds),
                'percentage' => 0,
                'article_status' => array_fill(0, count($articleIds), 'pending')
            ];
        }

        $progressRecords = UserProgress::where('user_id', $userId)
            ->whereIn('law_article_id', $articleIds)
            ->get()
            ->keyBy('law_article_id');

        $totalArticles = count($articleIds);
        $articleStatus = [];
        $completedCount = 0;

        foreach ($articleIds as $articleId) {
            if (isset($progressRecords[$articleId])) {
                $progress = $progressRecords[$articleId];
                if ($progress->is_completed) {
                    $articleStatus[] = 'correct';
                    $completedCount++;
                } else {
                    $articleStatus[] = 'incorrect';
                }
            } else {
                $articleStatus[] = 'pending';
            }
        }

        $progressPercentage = $totalArticles > 0 ? round(($completedCount / $totalArticles) * 100, 2) : 0;

        return [
            'completed' => $completedCount,
            'total' => $totalArticles,
            'percentage' => $progressPercentage,
            'article_status' => $articleStatus
        ];
    }

    
    /**
     * Verifica se a revisão anterior está completa
     */
    private function isPreviousRevisionComplete($userId, LegalReference $reference, int $currentIndex): bool
    {
        $articles = $reference->articles()
            ->orderBy('position', 'asc')
            ->where('is_active', true)
            ->get();
            
        $chunks = $articles->chunk(self::ARTICLES_PER_PHASE);
        
        // Encontrar a revisão anterior
        $revisionNumber = floor($currentIndex / self::PHASES_BEFORE_REVISION);
        $revisionKey = "revision_{$reference->uuid}_{$revisionNumber}";
        $revisionArticleIds = session($revisionKey);
        
        if (!$revisionArticleIds) {
            return false;
        }
        
        $progress = $this->getRevisionProgress($userId, $revisionArticleIds);
        return $progress['completed'] === count($revisionArticleIds);
    }

    /**
     * Verifica se a fase anterior está completa
     */
    private function isPreviousPhaseComplete($userId, LegalReference $reference, int $currentIndex): bool
    {
        if ($currentIndex === 0) return true;
        
        $articles = $reference->articles()
            ->orderBy('position', 'asc')
            ->where('is_active', true)
            ->get();
            
        $chunks = $articles->chunk(self::ARTICLES_PER_PHASE);
        
        if (!isset($chunks[$currentIndex - 1])) return true;
        
        $previousPhaseArticles = $chunks[$currentIndex - 1];
        $progress = $this->getPhaseProgress($userId, $previousPhaseArticles);
        
        return $progress['completed'] === $progress['total'];
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
    /**
   /**
     * Recompensa o usuário com uma vida após assistir o anúncio
     */
    public function rewardLife()
    {
        $user = Auth::user();
        // Adiciona uma vida, máximo de 5
        $newLives = min($user->lives + 1, 5);
        User::where('id', $user->id)->update(['lives' => $newLives]);
        $user = Auth::user(); // Buscar usuário atualizado

        return response()->json([
            'success' => true,
            'user' => [
                'lives' => $user->lives
            ]
        ]);
    }
}
