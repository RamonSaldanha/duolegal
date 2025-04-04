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
    const REVIEW_PHASE_INTERVAL = 2;

    /**
     * Exibir o mapa de fases do jogo
     */
    public function map()
    {
        $user = Auth::user();
        $userId = $user->id;

        // Verifica se o usuário tem vidas ou é assinante
        // Assinantes sempre podem jogar, mesmo sem vidas
        if (!$user->hasLives() && !$user->hasActiveSubscription()) {
            return redirect()->route('play.nolives');
        }

        // Verificar se o usuário tem leis preferidas
        $hasPreferences = $user->legalReferences()->exists();

        // Buscar referências legais com seus artigos
        $legalReferencesQuery = LegalReference::with(['articles' => function($query) {
            $query->orderBy('position', 'asc')->where('is_active', true);
        }]);

        // Se o usuário tem preferências, filtrar apenas as leis selecionadas
        if ($hasPreferences) {
            $legalReferencesQuery->whereHas('users', function($query) use ($userId) {
                $query->where('users.id', $userId);
            });
        }

        $legalReferences = $legalReferencesQuery->orderBy('id', 'asc')->get();

        // Se não há leis para mostrar (nenhuma preferência ou nenhuma lei cadastrada)
        if ($legalReferences->isEmpty()) {
            if ($hasPreferences) {
                // Usuário tem preferências, mas nenhuma lei associada (situação improvável)
                return redirect()->route('user.legal-references.index')
                    ->with('message', 'Você precisa selecionar pelo menos uma lei para estudar.');
            } else {
                // Usuário não tem preferências, redirecionar para a página de seleção
                return redirect()->route('user.legal-references.index')
                    ->with('message', 'Selecione as leis que deseja estudar.');
            }
        }

        // Preparar dados das fases
        $phases = [];
        $phaseCount = 0;
        $isLawBlocked = false;

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

            // Verificar se existem artigos não completados (percentage < 100) PARA ESTA REFERÊNCIA
            $referenceArticleIds = $reference->articles->pluck('id');
            $hasIncompleteArticlesInThisReference = UserProgress::where('user_id', $userId)
                ->whereIn('law_article_id', $referenceArticleIds)
                ->where('percentage', '<', 100)
                ->exists();
            // Agrupar artigos em grupos de ARTICLES_PER_PHASE
            $chunks = $reference->articles->chunk(self::ARTICLES_PER_PHASE);
            $phaseMap = []; // Armazena o mapeamento entre números de fase e chunks
            $regularPhaseCounter = 0;
            $chunkIndex = 0; // Reinicia índice para cada referência

            foreach ($chunks as $index => $articleChunk) {
                // Use $chunkIndex em vez de $index
                $regularPhaseCounter++;
                $phaseCount++;

                $progress = $this->getPhaseProgress($userId, $articleChunk);

                // Armazenar o mapeamento
                $phaseMap[$phaseCount] = $chunkIndex;

                // Verificar se a fase está completa - artigos sem status "pending"
                $pendingArticles = array_filter($progress['article_status'], function($status) {
                    return $status === 'pending';
                });

                $isPhaseComplete = empty($pendingArticles);

                // Se a última fase adicionada foi uma revisão e há artigos incompletos NESTA REFERÊNCIA,
                // bloquear esta fase, a menos que ela já esteja completa
                $isBlockedAfterReview = $lastPhaseWasReview && $hasIncompleteArticlesInThisReference && !$isPhaseComplete;
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
                    'is_blocked' => $isBlocked,
                    'is_review' => false,
                    'chunk_index' => $chunkIndex, // Adiciona o índice do chunk para debug
                ];

                $lastPhaseWasReview = false;

                // Inserir fase de revisão após cada REVIEW_PHASE_INTERVAL fases regulares
                if ($regularPhaseCounter % self::REVIEW_PHASE_INTERVAL === 0) {
                    $phaseCount++;
                    // Fase de revisão não mapeia para nenhum chunk específico
                    $phaseMap[$phaseCount] = 'review';

                    $phases[] = [
                        'id' => $phaseCount,
                        'title' => 'Revisão ' . ceil($regularPhaseCounter / self::REVIEW_PHASE_INTERVAL),
                        'reference_name' => $reference->name,
                        'reference_uuid' => $reference->uuid,
                        'article_count' => 0, // Zero artigos em fases de revisão
                        'difficulty' => $this->calculateAverageDifficulty($articleChunk),
                        'first_article' => null,
                        'phase_number' => $phaseCount,
                        'is_complete' => false,
                        'progress' => [
                            'completed' => 0,
                            'total' => 0,
                            'percentage' => 0,
                            'article_status' => [] // Array vazio para status de artigos
                        ],
                        'is_blocked' => ($this->getCurrentPhase() != $phaseCount) || !$hasIncompleteArticlesInThisReference,
                        'is_review' => true,
                    ];
                    $lastPhaseWasReview = true;
                }
                // Incremente o índice do chunk manualmente
                $chunkIndex++;
            }
        }

        return Inertia::render('Play/Map', [
            'phases' => $phases,
            'currentPhaseNumber' => $this->getCurrentPhase(),
            'user' => [
                'lives' => $user->lives,
                'has_infinite_lives' => $user->hasInfiniteLives()
            ]
        ]);
    }

    private function getCurrentPhase() {
        $user = Auth::user();
        $userId = $user->id;

        // Verificar se o usuário tem leis preferidas
        $hasPreferences = $user->legalReferences()->exists();

        // Query base para UserProgress
        $query = UserProgress::where('user_id', $userId);

        // Se o usuário tem preferências, filtrar apenas os artigos das leis selecionadas
        if ($hasPreferences) {
            // Buscar os IDs dos artigos relacionados às leis de preferência do usuário
            $userLegalReferences = $user->legalReferences()->pluck('legal_references.id')->toArray();

            $preferredArticleIds = LawArticle::whereHas('legalReference', function($q) use ($userLegalReferences) {
                $q->whereIn('legal_reference_id', $userLegalReferences);
            })->pluck('id')->toArray();

            // Aplicar filtro à consulta para considerar apenas artigos das leis preferidas
            $query->whereIn('law_article_id', $preferredArticleIds);
        }

        // Contar apenas os artigos relevantes
        $currentArticleCount = $query->count();

        // Calcula quantas fases regulares foram completadas
        $regularPhasesCompleted = ceil($currentArticleCount / self::ARTICLES_PER_PHASE);

        // Calcula quantas fases de revisão já foram completadas
        $reviewPhasesCompleted = floor($regularPhasesCompleted / self::REVIEW_PHASE_INTERVAL);

        // Fase base (soma das fases regulares + revisões completadas)
        $currentPhase = $regularPhasesCompleted + $reviewPhasesCompleted;

        // Verifica se a fase atual é uma fase de revisão (após completar REVIEW_PHASE_INTERVAL fases regulares)
        if ($regularPhasesCompleted % self::REVIEW_PHASE_INTERVAL === 0 && $regularPhasesCompleted > 0) {
            // Verifica se existem artigos incompletos para revisar (também filtrados por preferências)
            $hasIncompleteArticlesQuery = UserProgress::where('user_id', $userId)
                ->where('percentage', '<', 100);

            // Aplicar o mesmo filtro de preferências, se necessário
            if ($hasPreferences && !empty($preferredArticleIds)) {
                $hasIncompleteArticlesQuery->whereIn('law_article_id', $preferredArticleIds);
            }

            $hasIncompleteArticles = $hasIncompleteArticlesQuery->exists();

            // Se não há artigos incompletos, o usuário já passou pela fase de revisão
            if (!$hasIncompleteArticles) {
                $currentPhase++;
            }
        }

        // A fase atual é pelo menos 1
        return (int)max(1, $currentPhase);
    }

    /**
     * Visualizar os detalhes de uma fase
     */
    public function phase($referenceUuid, $phaseNumber)
    {
        $user = Auth::user();
        $userId = $user->id;

        // Verificações iniciais
        // Assinantes sempre podem jogar, mesmo sem vidas
        if (!$user->hasLives() && !$user->hasActiveSubscription()) {
            return redirect()->route('play.nolives');
        }

        // Verificar se o usuário tem leis preferidas
        $hasPreferences = $user->legalReferences()->exists();

        // Buscar referências legais com filtro por preferências
        $legalReferencesQuery = LegalReference::with(['articles' => function($query) {
            $query->orderBy('position', 'asc')->where('is_active', true);
        }]);

        // Se o usuário tem preferências, filtrar apenas as leis selecionadas
        if ($hasPreferences) {
            $legalReferencesQuery->whereHas('users', function($query) use ($userId) {
                $query->where('users.id', $userId);
            });
        }

        $allLegalReferences = $legalReferencesQuery->orderBy('id', 'asc')->get();

        // Se não há leis para mostrar, redirecionar para a seleção
        if ($allLegalReferences->isEmpty()) {
            return redirect()->route('user.legal-references.index')
                ->with('message', 'Selecione as leis que deseja estudar.');
        }

        // Criar mapeamento global de fases para referências e chunks
        $globalPhaseMap = [];
        $globalPhaseCount = 0;
        $referenceIndex = 0;
        $phaseFound = false;

        foreach ($allLegalReferences as $reference) {
            $chunks = $reference->articles->chunk(self::ARTICLES_PER_PHASE);
            $regularPhaseCounter = 0;

            foreach ($chunks as $chunkIndex => $articleChunk) {
                $regularPhaseCounter++;
                $globalPhaseCount++;

                // Mapear fase regular
                $globalPhaseMap[$globalPhaseCount] = [
                    'reference_uuid' => $reference->uuid,
                    'chunk_index' => $chunkIndex,
                    'is_review' => false
                ];

                // Se encontramos a fase solicitada, armazene a referência e o índice
                if ($globalPhaseCount == $phaseNumber) {
                    $phaseFound = true;
                    $referenceUuid = $reference->uuid; // Use a referência correta
                }

                // Inserir fase de revisão após cada REVIEW_PHASE_INTERVAL fases regulares
                if ($regularPhaseCounter % self::REVIEW_PHASE_INTERVAL === 0) {
                    $globalPhaseCount++;

                    $globalPhaseMap[$globalPhaseCount] = [
                        'reference_uuid' => $reference->uuid,
                        'is_review' => true
                    ];

                    if ($globalPhaseCount == $phaseNumber) {
                        $phaseFound = true;
                        $referenceUuid = $reference->uuid;
                    }
                }
            }
            $referenceIndex++;
        }

        // Se a fase não foi encontrada no mapeamento global
        if (!$phaseFound) {
            return redirect()->route('play.map')->with('message', 'Fase não encontrada');
        }

        // Se é uma fase de revisão, redirecionar para review
        if ($globalPhaseMap[$phaseNumber]['is_review']) {
            return redirect()->route('play.review', [
                'referenceUuid' => $referenceUuid,
                'phaseNumber' => $phaseNumber
            ]);
        }

        // Carregar a referência e artigos
        $reference = LegalReference::where('uuid', $referenceUuid)->firstOrFail();

        $allArticles = $reference->articles()
            ->orderBy('position', 'asc')
            ->where('is_active', true)
            ->get();

        $chunkedArticles = $allArticles->chunk(self::ARTICLES_PER_PHASE);
        $chunkIndex = $globalPhaseMap[$phaseNumber]['chunk_index'];

        // Verificar se este chunk existe
        if (!isset($chunkedArticles[$chunkIndex]) || $chunkedArticles[$chunkIndex]->isEmpty()) {
            return redirect()->route('play.map')->with('message', 'Fase não encontrada');
        }

        $phaseArticles = $chunkedArticles[$chunkIndex];

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

        // Verificar se existe próxima fase
        $nextPhaseNumber = $phaseNumber + 1;
        $hasNextPhase = isset($globalPhaseMap[$nextPhaseNumber]);

        // Verificar se a próxima fase é uma fase de revisão
        $nextPhaseIsReview = $hasNextPhase && $globalPhaseMap[$nextPhaseNumber]['is_review'];

        // Verificar se existem artigos para revisar
        $hasArticlesToReview = false;
        if ($nextPhaseIsReview) {
            // Buscar os IDs dos artigos da referência
            $articleIds = $reference->articles()
                ->where('is_active', true)
                ->pluck('id');

            // Verificar se existem artigos incompletos para o usuário
            $hasArticlesToReview = UserProgress::where('user_id', $user->id)
                ->whereIn('law_article_id', $articleIds)
                ->where('percentage', '<', 100)
                ->exists();
        }

        return Inertia::render('Play/Phase', [
            'phase' => [
                'title' => 'Fase ' . $phaseNumber . ': ' . $reference->name,
                'reference_name' => $reference->name,
                'phase_number' => (int)$phaseNumber,
                'difficulty' => $this->calculateAverageDifficulty($phaseArticles),
                'progress' => $this->getPhaseProgress(Auth::id(), $phaseArticles),
                'has_next_phase' => $hasNextPhase,
                'next_phase_number' => $hasNextPhase ? $nextPhaseNumber : null,
                'next_phase_is_review' => $nextPhaseIsReview,
                'has_articles_to_review' => $hasArticlesToReview,
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
                'lives' => $user->lives,
                'has_infinite_lives' => $user->hasInfiniteLives()
            ],
            'should_redirect' => !$user->hasInfiniteLives() && $user->lives <= 0,
            'redirect_url' => !$user->hasInfiniteLives() && $user->lives <= 0 ? route('play.nolives') : null
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

        $completedCount = $totalArticles - count($pendingArticles);
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
                'lives' => $user->lives,
                'has_infinite_lives' => $user->hasInfiniteLives()
            ]
        ]);
    }

    /**
     * Exibir a fase de revisão para uma referência legal
     */
    public function review($referenceUuid, $phaseNumber)
    {
        $phaseNumber = (int)$phaseNumber;

        $user = Auth::user();
        $userId = $user->id;

        // Verifica se o usuário tem vidas disponíveis ou é assinante
        // Assinantes sempre podem jogar, mesmo sem vidas
        if (!$user->hasLives() && !$user->hasActiveSubscription()) {
            return redirect()->route('play.nolives');
        }

        // Verificar se o usuário tem leis preferidas
        $hasPreferences = $user->legalReferences()->exists();

        // Verificar se a referência está nas preferências do usuário
        if ($hasPreferences) {
            $referenceExists = $user->legalReferences()
                ->where('legal_references.uuid', $referenceUuid)
                ->exists();

            if (!$referenceExists) {
                return redirect()->route('user.legal-references.index')
                    ->with('message', 'Esta lei não está nas suas preferências de estudo.');
            }
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
        $hasArticlesToReview = UserProgress::where('user_id', $user->id)
            ->whereIn('law_article_id', $articleIds)
            ->where('percentage', '<', 100)
            ->exists();
        // Mapear as fases para determinar qual é a próxima após esta revisão
        $allArticles = $reference->articles()
            ->orderBy('position', 'asc')
            ->where('is_active', true)
            ->get();

        // Agrupar os artigos em fases
        $chunkedArticles = $allArticles->chunk(self::ARTICLES_PER_PHASE);

        // Criar um mapeamento entre números de fase e índices de chunks
        $phaseToChunkMap = [];
        $regularPhaseCount = 0;
        $phaseCount = 0;

        foreach ($chunkedArticles as $index => $chunk) {
            $regularPhaseCount++;
            $phaseCount++;

            // Mapear fase regular ao índice do chunk
            $phaseToChunkMap[$phaseCount] = $index;

            // Se esta fase regular completou um intervalo de revisão,
            // adicionar uma fase de revisão (que não mapeia para nenhum chunk)
            if ($regularPhaseCount % self::REVIEW_PHASE_INTERVAL === 0) {
                $phaseCount++;
                // Fase de revisão não mapeia para nenhum chunk específico
                $phaseToChunkMap[$phaseCount] = 'review';
            }
        }

        // Encontrar a próxima fase após a fase de revisão atual
        $nextPhaseNumber = $phaseNumber + 1;
        $hasNextPhase = isset($phaseToChunkMap[$nextPhaseNumber]);

        // Se não há artigos incompletos, redirecionar para a PRÓXIMA fase após esta fase de revisão
        if ($incompleteArticleIds->isEmpty()) {
            // Se existe uma próxima fase, redirecionar para ela
            if ($hasNextPhase) {
                return redirect()->route('play.phase', [
                    'reference' => $referenceUuid,
                    'phase' => $nextPhaseNumber
                ]);
            } else {
                // Se não existe próxima fase, volta para o mapa
                return redirect()
                    ->route('play.map')
                    ->with('message', 'Não há mais fases nesta referência legal. Parabéns por completar todas!');
            }
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
                'reference_uuid' => $referenceUuid,
                'has_next_phase' => $hasNextPhase,
                'next_phase_number' => $hasNextPhase ? $nextPhaseNumber : null,
                'next_phase_is_review' => false // A próxima fase depois de uma revisão é sempre uma fase regular
            ],
            'articles' => $articlesWithProgress
        ]);
    }
}
