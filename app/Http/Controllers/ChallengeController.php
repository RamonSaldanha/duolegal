<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\ChallengeProgress;
use App\Models\LegalReference;
use App\Models\LawArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ChallengeController extends Controller
{
    // Listar desafios públicos
    public function index(Request $request)
    {
        $search = $request->get('search');
        
        $challenges = Challenge::with('creator')
            ->public()
            ->when($search, function ($query, $search) {
                return $query->where('title', 'like', '%' . $search . '%')
                           ->orWhere('description', 'like', '%' . $search . '%');
            })
            ->withCount('challengeProgress as participants_count')
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        // Adicionar estatísticas para cada desafio
        $challenges->getCollection()->transform(function ($challenge) {
            $stats = $challenge->getStats();
            $challenge->stats = $stats;
            return $challenge;
        });

        return Inertia::render('Challenges/Index', [
            'challenges' => $challenges,
            'search' => $search
        ]);
    }

    // Exibir página de criação de desafio
    public function create()
    {
        $legalReferences = LegalReference::with(['articles' => function($query) {
            $query->where('is_active', true)
                  ->orderByRaw('CAST(article_reference AS UNSIGNED) ASC');
        }])->where('is_active', true)->get();

        return Inertia::render('Challenges/Create', [
            'legalReferences' => $legalReferences
        ]);
    }

    // Criar novo desafio
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'selected_articles' => 'required|array|min:1',
            'selected_articles.*' => 'exists:law_articles,id',
            'is_public' => 'boolean'
        ]);

        $challenge = Challenge::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'created_by' => Auth::id(),
            'selected_articles' => $validated['selected_articles'],
            'is_public' => $validated['is_public'] ?? false,
            'is_active' => true
        ]);

        return redirect()->route('challenges.show', $challenge->uuid)
                        ->with('success', 'Desafio criado com sucesso!');
    }

    // Exibir detalhes do desafio
    public function show(Challenge $challenge)
    {
        if (!$challenge->canAccess(Auth::user())) {
            abort(403, 'Você não tem permissão para acessar este desafio.');
        }

        $challenge->load('creator');
        $stats = $challenge->getStats();
        
        // Verificar se o usuário já está participando
        $userProgress = null;
        if (Auth::check()) {
            $userProgress = ChallengeProgress::getUserChallengeProgress(Auth::id(), $challenge->id);
        }

        // Obter artigos do desafio com informações de referência legal
        $challengeArticles = $challenge->getSelectedArticles()->map(function($article) {
            return [
                'id' => $article->id,
                'article_reference' => $article->article_reference,
                'difficulty_level' => $article->difficulty_level,
                'legal_reference_name' => $article->legalReference->name
            ];
        });

        return Inertia::render('Challenges/Show', [
            'challenge' => $challenge,
            'stats' => $stats,
            'userProgress' => $userProgress,
            'isParticipating' => !empty($userProgress),
            'challengeArticles' => $challengeArticles
        ]);
    }

    // Iniciar participação no desafio
    public function join(Challenge $challenge)
    {
        if (!$challenge->canAccess(Auth::user())) {
            abort(403, 'Você não tem permissão para acessar este desafio.');
        }

        $user = Auth::user();
        
        // Verificar se o usuário já tem progresso registrado neste desafio
        $existingProgress = ChallengeProgress::where('user_id', $user->id)
            ->where('challenge_id', $challenge->id)
            ->exists();
        
        // Se não tem progresso, criar um registro inicial para marcar participação
        if (!$existingProgress) {
            $firstArticle = $challenge->getSelectedArticles()->first();
            if ($firstArticle) {
                ChallengeProgress::create([
                    'user_id' => $user->id,
                    'challenge_id' => $challenge->id,
                    'law_article_id' => $firstArticle->id,
                    'correct_answers' => 0,
                    'wrong_answers' => 0,
                    'percentage' => 0,
                    'attempts' => 0,
                    'best_score' => 0,
                    'is_completed' => false
                ]);
            }
        }

        // Redirecionar para o mapa do desafio
        return redirect()->route('challenges.map', $challenge->uuid);
    }

    // Mapa do desafio (usando lógica idêntica ao PlayController::map)
    public function map(Challenge $challenge)
    {
        if (!$challenge->canAccess(Auth::user())) {
            abort(403, 'Você não tem permissão para acessar este desafio.');
        }

        $user = Auth::user();
        $userId = $user->id;

        // Obter artigos do desafio
        $challengeArticles = $challenge->getSelectedArticles();
        
        if ($challengeArticles->isEmpty()) {
            return redirect()->route('challenges.show', $challenge->uuid)
                           ->with('error', 'Este desafio não possui artigos válidos.');
        }

        // === APLICAR LÓGICA IDÊNTICA DO PLAYCONTROLLER ===
        
        // Agrupar artigos por referência legal (simular legalReferences)
        $legalReferencesData = [];
        foreach ($challengeArticles as $article) {
            $refUuid = $article->legalReference->uuid;
            if (!isset($legalReferencesData[$refUuid])) {
                $legalReferencesData[$refUuid] = [
                    'reference' => $article->legalReference,
                    'articles' => collect()
                ];
            }
            $legalReferencesData[$refUuid]['articles']->push($article);
        }

        // Usar as mesmas constantes do PlayController
        $ARTICLES_PER_PHASE = 6; // ou usar PlayController::ARTICLES_PER_PHASE se for public
        $REVIEW_PHASE_INTERVAL = 3;
        $PHASES_PER_MODULE_PER_LAW = 6;

        // === PASSO 1: Construir estrutura de fases INTERCALADAS ===
        $phaseStructureList = [];
        $lawChunksData = [];
        $maxChunks = 0;
        
        foreach ($legalReferencesData as $refUuid => $refData) {
            $chunks = $refData['articles']->chunk($ARTICLES_PER_PHASE);
            $lawChunksData[$refUuid] = [
                'reference' => $refData['reference'],
                'chunks' => $chunks,
                'total_chunks' => $chunks->count()
            ];
            $maxChunks = max($maxChunks, $chunks->count());
        }
        
        // Intercalar fases por módulo (lógica do PlayController)
        $tempCounter = 0;
        $totalModules = ceil($maxChunks / $PHASES_PER_MODULE_PER_LAW);
        
        for ($moduleIndex = 0; $moduleIndex < $totalModules; $moduleIndex++) {
            $startChunkIndex = $moduleIndex * $PHASES_PER_MODULE_PER_LAW;
            $endChunkIndex = $startChunkIndex + $PHASES_PER_MODULE_PER_LAW;
            
            foreach ($lawChunksData as $lawUuid => $lawData) {
                $phasesAddedForThisLaw = 0;
                
                for ($chunkIndex = $startChunkIndex; $chunkIndex < $endChunkIndex && $phasesAddedForThisLaw < $PHASES_PER_MODULE_PER_LAW; $chunkIndex++) {
                    if ($chunkIndex < $lawData['total_chunks']) {
                        $tempCounter++;
                        
                        $phaseStructureList[] = [
                            'id' => $tempCounter,
                            'is_review' => false,
                            'reference_uuid' => $lawUuid,
                            'chunk_index' => $chunkIndex,
                            'article_chunk' => $lawData['chunks'][$chunkIndex]
                        ];
                        
                        $phasesAddedForThisLaw++;
                    }
                }
                
                if ($phasesAddedForThisLaw > 0) {
                    $totalRegularPhasesOfThisLaw = min($endChunkIndex, $lawData['total_chunks']);
                    
                    if ($totalRegularPhasesOfThisLaw % $REVIEW_PHASE_INTERVAL === 0) {
                        $tempCounter++;
                        $phaseStructureList[] = [
                            'id' => $tempCounter,
                            'is_review' => true,
                            'reference_uuid' => $lawUuid,
                            'last_regular_counter' => $totalRegularPhasesOfThisLaw
                        ];
                    }
                }
            }
        }

        // === PASSO 2: Calcular progresso e bloqueios ===
        $phasesData = [];
        $currentPhaseId = null;
        $blockSubsequent = false;
        $previousPhaseIsComplete = true;
        $currentLawUuid = null;
        $lawCompletionStatus = [];

        foreach ($phaseStructureList as $phaseStruct) {
            $currentPhaseGlobalId = $phaseStruct['id'];

            // Lógica de mudança de lei
            if ($phaseStruct['reference_uuid'] !== $currentLawUuid) {
                $previousLawUuid = $currentLawUuid;
                $currentLawUuid = $phaseStruct['reference_uuid'];
                
                if ($previousLawUuid !== null && isset($lawCompletionStatus[$previousLawUuid]) && !$lawCompletionStatus[$previousLawUuid]) {
                    $blockSubsequent = true;
                }
                $lawCompletionStatus[$currentLawUuid] = true;
            }

            $isPhaseBlocked = $blockSubsequent || !$previousPhaseIsComplete;
            $isPhaseCurrent = false;
            $isPhaseComplete = false;
            
            $reference = $lawChunksData[$currentLawUuid]['reference'];

            if ($phaseStruct['is_review']) {
                // Fase de revisão
                $articleIdsInScope = $this->getChallengeArticlesInScopeForReview($currentPhaseGlobalId, $phaseStructureList, $challenge->id);
                $phaseProgress = $this->getChallengeReviewPhaseProgress($userId, $challenge->id, $articleIdsInScope);
                $isPhaseComplete = $phaseProgress['is_complete'];

                if (!$isPhaseBlocked && !$isPhaseComplete && $currentPhaseId === null) {
                    $isPhaseCurrent = true;
                    $currentPhaseId = $currentPhaseGlobalId;
                }

                $phaseBuiltData = $this->buildChallengeReviewPhaseData(
                    $currentPhaseGlobalId,
                    $reference,
                    $phaseStruct['last_regular_counter'],
                    $isPhaseBlocked,
                    $isPhaseCurrent,
                    $phaseProgress
                );
            } else {
                // Fase regular
                $phaseProgress = $this->getChallengePhaseProgress($userId, $challenge->id, $phaseStruct['article_chunk']);
                $isPhaseComplete = $phaseProgress['all_attempted'];

                if (!$isPhaseBlocked && !$isPhaseComplete && $currentPhaseId === null) {
                    $isPhaseCurrent = true;
                    $currentPhaseId = $currentPhaseGlobalId;
                }

                $phaseBuiltData = $this->buildChallengePhaseData(
                    $currentPhaseGlobalId,
                    $reference,
                    $phaseStruct['article_chunk'],
                    $phaseStruct['chunk_index'],
                    $phaseProgress,
                    $isPhaseBlocked,
                    $isPhaseCurrent
                );
            }

            if (!$isPhaseComplete) {
                $lawCompletionStatus[$currentLawUuid] = false;
            }

            if ($phaseBuiltData) {
                $phasesData[] = $phaseBuiltData;
            }

            $previousPhaseIsComplete = $isPhaseComplete;
            if ($isPhaseCurrent) {
                $blockSubsequent = true;
            }
        }

        // === PASSO 3: Ajuste final ===
        if ($currentPhaseId !== null) {
            foreach ($phasesData as $index => $phase) {
                if ($phase['id'] === $currentPhaseId) {
                    $phasesData[$index]['is_current'] = true;
                    $phasesData[$index]['is_blocked'] = false;
                } elseif ($phasesData[$index]['is_current']) {
                    $phasesData[$index]['is_current'] = false;
                }
            }
        }

        // Organizar em módulos (se múltiplas leis)
        $modulesData = $this->organizeChallengePhases($phasesData);

        // Obter usuários por fase (apenas para desafios)
        $usersPerPhase = $this->getChallengeUsersPerPhase($challenge);

        return Inertia::render('Play/Map', [ // USAR O MESMO COMPONENTE!
            'phases' => $phasesData,
            'modules' => $modulesData,
            'journey' => null, // Desafios não usam jornadas
            'user' => [
                'lives' => $user->lives,
                'has_infinite_lives' => $user->hasInfiniteLives()
            ],
            // Adicionar flag para identificar que é um desafio
            'is_challenge' => true,
            'challenge' => $challenge,
            // Adicionar dados de usuários por fase
            'users_per_phase' => $usersPerPhase
        ]);
    }

    // Fase do desafio (baseado no PlayController::phase mas para desafios)
    public function phase(Challenge $challenge, $phaseNumber)
    {
        if (!$challenge->canAccess(Auth::user())) {
            abort(403, 'Você não tem permissão para acessar este desafio.');
        }

        $user = Auth::user();
        $userId = $user->id;
        $phaseNumber = (int) $phaseNumber;

        // Obter informações sobre a fase (se é revisão ou não)
        $phaseInfo = $this->getChallengePhaseInfo($challenge, $phaseNumber);
        
        if (!$phaseInfo) {
            return redirect()->route('challenges.map', $challenge->uuid)
                           ->with('error', 'Fase não encontrada.');
        }

        // Obter todos os artigos da fase usando a mesma lógica do mapa
        $phaseArticles = $this->getChallengePhaseArticles($challenge, $phaseNumber);
        
        if ($phaseArticles->isEmpty()) {
            return redirect()->route('challenges.map', $challenge->uuid)
                           ->with('error', 'Nenhum artigo encontrado para esta fase.');
        }

        // Obter primeira referência legal para o título
        $firstArticle = $phaseArticles->first();
        $reference = $firstArticle->legalReference;

        // Preparar todos os artigos da fase com progresso
        $articlesWithProgress = [];
        foreach ($phaseArticles as $article) {
            $articleProgress = ChallengeProgress::where('user_id', $userId)
                                              ->where('challenge_id', $challenge->id)
                                              ->where('law_article_id', $article->id)
                                              ->first();

            $articlesWithProgress[$article->uuid] = [
                'uuid' => $article->uuid,
                'article_reference' => $article->article_reference,
                'original_content' => $article->original_content,
                'practice_content' => $article->practice_content,
                'options' => $article->options->map(fn($o) => [
                    'id' => $o->id,
                    'word' => $o->word,
                    'is_correct' => $o->is_correct,
                    'gap_order' => $o->gap_order,
                    'position' => $o->position
                ])->sortBy('position')->values()->all(),
                'progress' => $articleProgress ? [
                    'percentage' => $articleProgress->percentage,
                    'is_completed' => $articleProgress->is_completed,
                    'best_score' => $articleProgress->best_score,
                    'attempts' => $articleProgress->attempts,
                    'wrong_answers' => $articleProgress->wrong_answers,
                ] : null,
            ];
        }

        // Verificar se há próxima fase
        $hasNextPhase = $this->hasNextChallengePhase($challenge, $phaseNumber);
        $nextPhaseNumber = $hasNextPhase ? $this->getNextChallengePhaseNumber($challenge, $phaseNumber) : null;
        
        // Determinar o título baseado se é fase de revisão ou não
        $phaseTitle = $phaseInfo['is_review'] ? 'Fase ' . $phaseNumber . ' - Revisão' : 'Fase ' . $phaseNumber;
        
        return Inertia::render('Play/Phase', [ // USAR O MESMO COMPONENTE!
            'phase' => [
                'title' => $phaseTitle,
                'reference_name' => $reference->name,
                'phase_number' => $phaseNumber,
                'difficulty' => $this->calculateAverageDifficulty($phaseArticles),
                'has_next_phase' => $hasNextPhase,
                'next_phase_number' => $nextPhaseNumber,
                'is_review' => $phaseInfo['is_review'],
                'reference_uuid' => $reference->uuid,
            ],
            'articles' => $articlesWithProgress,
            // Adicionar flags para identificar que é um desafio
            'is_challenge' => true,
            'challenge' => [
                'uuid' => $challenge->uuid,
                'title' => $challenge->title,
                'description' => $challenge->description,
            ]
        ]);
    }

    // Salvar progresso no desafio
    public function saveProgress(Request $request, Challenge $challenge)
    {
        if (!$challenge->canAccess(Auth::user())) {
            abort(403, 'Você não tem permissão para acessar este desafio.');
        }

        $validated = $request->validate([
            'article_uuid' => 'required|string|exists:law_articles,uuid',
            'correct_answers' => 'required|integer|min:0',
            'total_answers' => 'required|integer|min:1',
        ]);

        $article = LawArticle::where('uuid', $validated['article_uuid'])->firstOrFail();
        $user = Auth::user();

        // Verificar se o artigo faz parte do desafio
        if (!in_array($article->id, $challenge->selected_articles)) {
            return response()->json(['error' => 'Artigo não faz parte deste desafio.'], 400);
        }

        $correctAnswers = min((int)$validated['correct_answers'], (int)$validated['total_answers']);
        $totalAnswers = (int)$validated['total_answers'];
        $percentage = ($correctAnswers / $totalAnswers) * 100;

        // Perder vida se necessário (mesmo sistema do jogo vanilla)
        $lostLife = false;
        if ($percentage < 70) {
            if ($user->lives > 0 && !$user->hasInfiniteLives()) {
                $user->decrementLife();
                $lostLife = true;
            }
        }

        // Atualizar progresso do desafio
        $progress = ChallengeProgress::updateChallengeProgress(
            $user->id,
            $challenge->id,
            $article->id,
            $correctAnswers,
            $totalAnswers
        );

        // Calcular XP (mesmo sistema do vanilla)
        $wasAlreadyCompleted = $progress->attempts > 1 && $progress->is_completed;
        $xpGained = 0;
        if ($percentage >= 70 && !$wasAlreadyCompleted) {
            $xpGained = \App\Models\User::calculateXpGain($article->difficulty_level);
            $user->addXp($xpGained);
        }

        return response()->json([
            'success' => true,
            'progress' => [
                'percentage' => $progress->percentage,
                'is_completed' => $progress->is_completed,
                'best_score' => $progress->best_score,
                'attempts' => $progress->attempts,
                'wrong_answers' => $progress->wrong_answers,
            ],
            'user' => [
                'lives' => $user->lives,
                'xp' => $user->xp,
                'has_infinite_lives' => $user->hasInfiniteLives()
            ],
            'xp_gained' => $xpGained,
            'lost_life' => $lostLife,
            'should_redirect' => !$user->hasInfiniteLives() && $user->lives <= 0,
            'redirect_url' => !$user->hasInfiniteLives() && $user->lives <= 0 ? route('play.nolives') : null
        ]);
    }

    // Meus desafios
    public function myIndex()
    {
        $user = Auth::user();
        
        $challenges = Challenge::where('created_by', $user->id)
                               ->withCount('challengeProgress as participants_count')
                               ->orderBy('created_at', 'desc')
                               ->paginate(10);

        // Adicionar estatísticas
        $challenges->getCollection()->transform(function ($challenge) {
            $stats = $challenge->getStats();
            $challenge->stats = $stats;
            return $challenge;
        });

        return Inertia::render('Challenges/MyIndex', [
            'challenges' => $challenges
        ]);
    }

    // Editar desafio
    public function edit(Challenge $challenge)
    {
        if ($challenge->created_by !== Auth::id()) {
            abort(403, 'Você não tem permissão para editar este desafio.');
        }

        $legalReferences = LegalReference::with(['articles' => function($query) {
            $query->where('is_active', true)
                  ->orderByRaw('CAST(article_reference AS UNSIGNED) ASC');
        }])->where('is_active', true)->get();

        return Inertia::render('Challenges/Edit', [
            'challenge' => $challenge,
            'legalReferences' => $legalReferences
        ]);
    }

    // Atualizar desafio
    public function update(Request $request, Challenge $challenge)
    {
        if ($challenge->created_by !== Auth::id()) {
            abort(403, 'Você não tem permissão para editar este desafio.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'selected_articles' => 'required|array|min:1',
            'selected_articles.*' => 'exists:law_articles,id',
            'is_public' => 'boolean'
        ]);

        $challenge->update($validated);

        return redirect()->route('challenges.show', $challenge->uuid)
                        ->with('success', 'Desafio atualizado com sucesso!');
    }

    // Excluir desafio
    public function destroy(Challenge $challenge)
    {
        if ($challenge->created_by !== Auth::id()) {
            abort(403, 'Você não tem permissão para excluir este desafio.');
        }

        $challenge->delete();

        return redirect()->route('challenges.my-index')
                        ->with('success', 'Desafio excluído com sucesso!');
    }

    // === MÉTODOS AUXILIARES PARA SIMULAR PLAYCONTROLLER ===

    private function getChallengePhaseProgress($userId, $challengeId, $articles)
    {
        if (!$userId || $articles->isEmpty()) {
            return [
                'completed' => 0, 'total' => $articles->count(), 'percentage' => 0,
                'article_status' => array_fill(0, $articles->count(), 'pending'),
                'is_fully_complete' => false, 'all_attempted' => false
            ];
        }

        $articleIds = $articles->pluck('id');
        $progressRecords = ChallengeProgress::where('user_id', $userId)
            ->where('challenge_id', $challengeId)
            ->whereIn('law_article_id', $articleIds)
            ->get()
            ->keyBy('law_article_id');

        $totalArticles = $articleIds->count();
        $articleStatus = [];
        $correctCount = 0;
        $attemptedCount = 0;

        foreach ($articles as $index => $article) {
            $progress = $progressRecords->get($article->id);
            if ($progress) {
                $attemptedCount++;
                if ($progress->percentage >= 100) {
                    $articleStatus[$index] = 'correct';
                    $correctCount++;
                } else {
                    $articleStatus[$index] = 'incorrect';
                }
            } else {
                $articleStatus[$index] = 'pending';
            }
        }

        $isFullyComplete = ($correctCount === $totalArticles);
        $allAttempted = ($attemptedCount === $totalArticles);
        $completedCount = $attemptedCount;
        $progressPercentage = $totalArticles > 0 ? round(($completedCount / $totalArticles) * 100, 2) : 0;

        return [
            'completed' => $completedCount,
            'total' => $totalArticles,
            'percentage' => $progressPercentage,
            'article_status' => array_values($articleStatus),
            'is_fully_complete' => $isFullyComplete,
            'all_attempted' => $allAttempted
        ];
    }

    private function getChallengeReviewPhaseProgress($userId, $challengeId, $articleIdsInScope)
    {
        if (!$userId || $articleIdsInScope->isEmpty()) {
            return [
                'is_complete' => true, 'needs_review' => false, 'articles_to_review_count' => 0,
                'completed' => 0, 'total' => 0, 'percentage' => 0, 'article_status' => [],
                'is_fully_complete' => true, 'all_attempted' => true
            ];
        }

        $incompleteArticlesCount = ChallengeProgress::where('user_id', $userId)
            ->where('challenge_id', $challengeId)
            ->whereIn('law_article_id', $articleIdsInScope)
            ->where('percentage', '<', 100)
            ->count();

        $needsReview = $incompleteArticlesCount > 0;

        return [
            'is_complete' => !$needsReview, 'needs_review' => $needsReview,
            'articles_to_review_count' => $incompleteArticlesCount,
            'completed' => 0, 'total' => $incompleteArticlesCount, 'percentage' => 0, 'article_status' => [],
            'is_fully_complete' => !$needsReview, 'all_attempted' => !$needsReview
        ];
    }

    private function getChallengeArticlesInScopeForReview($reviewPhaseId, $phaseStructureList, $challengeId)
    {
        $articleIds = collect();
        $referenceUuid = null;
        $currentReviewPhaseIndex = -1;

        // Encontrar fase de revisão
        foreach ($phaseStructureList as $index => $phaseStruct) {
            if ($phaseStruct['id'] === $reviewPhaseId && $phaseStruct['is_review']) {
                $referenceUuid = $phaseStruct['reference_uuid'];
                $currentReviewPhaseIndex = $index;
                break;
            }
        }

        if ($currentReviewPhaseIndex === -1 || $referenceUuid === null) {
            return collect();
        }

        // Coletar artigos do escopo
        $startIndex = 0;
        for ($i = $currentReviewPhaseIndex - 1; $i >= 0; $i--) {
            $phase = $phaseStructureList[$i];
            if ($phase['reference_uuid'] !== $referenceUuid || $phase['is_review']) {
                $startIndex = $i + 1;
                break;
            }
        }

        $endIndex = $currentReviewPhaseIndex - 1;

        for ($i = $startIndex; $i <= $endIndex; $i++) {
            if (isset($phaseStructureList[$i]) && !$phaseStructureList[$i]['is_review']) {
                $phase = $phaseStructureList[$i];
                if (isset($phase['article_chunk'])) {
                    $chunkArticleIds = $phase['article_chunk']->pluck('id');
                    $articleIds = $articleIds->merge($chunkArticleIds);
                }
            }
        }

        return $articleIds->unique();
    }

    private function buildChallengePhaseData($phaseId, $reference, $articleChunk, $chunkIndex, $progress, $isBlocked, $isCurrent)
    {
        return [
            'id' => $phaseId,
            'title' => 'Fase ' . $phaseId,
            'reference_name' => $reference->name,
            'reference_uuid' => $reference->uuid,
            'article_count' => $articleChunk->count(),
            'difficulty' => $this->calculateAverageDifficulty($articleChunk),
            'first_article' => $articleChunk->first()->uuid ?? null,
            'phase_number' => $phaseId,
            'chunk_index' => $chunkIndex,
            'is_complete' => $progress['all_attempted'] ?? false,
            'progress' => [
                'completed' => $progress['completed'] ?? 0,
                'total' => $progress['total'] ?? 0,
                'percentage' => $progress['percentage'] ?? 0,
                'is_fully_complete' => $progress['is_fully_complete'] ?? false,
                'all_attempted' => $progress['all_attempted'] ?? false,
                'has_errors' => isset($progress['article_status']) ? 
                    in_array('incorrect', $progress['article_status']) : false,
                'article_status' => $progress['article_status'] ?? []
            ],
            'is_blocked' => $isBlocked,
            'is_current' => $isCurrent,
            'is_review' => false,
        ];
    }

    private function buildChallengeReviewPhaseData($phaseId, $reference, $lastRegularPhaseCounter, $isBlocked, $isCurrent, $progress = null)
    {
        if ($progress === null) {
            $progress = [
                'is_complete' => true,
                'needs_review' => false,
                'articles_to_review_count' => 0,
            ];
        }

        return [
            'id' => $phaseId,
            'title' => 'Revisão ' . ceil($lastRegularPhaseCounter / 3), // REVIEW_PHASE_INTERVAL
            'reference_name' => $reference->name,
            'reference_uuid' => $reference->uuid,
            'article_count' => $progress['articles_to_review_count'] ?? 0,
            'difficulty' => 3,
            'first_article' => null,
            'phase_number' => $phaseId,
            'is_complete' => $progress['is_complete'] ?? true,
            'progress' => [
                'is_complete' => $progress['is_complete'] ?? true,
                'needs_review' => $progress['needs_review'] ?? false,
                'articles_to_review_count' => $progress['articles_to_review_count'] ?? 0,
                'completed' => 0,
                'total' => 0,
                'percentage' => 0,
                'is_fully_complete' => $progress['is_complete'] ?? true,
                'all_attempted' => $progress['is_complete'] ?? true,
            ],
            'is_blocked' => $isBlocked,
            'is_current' => $isCurrent,
            'is_review' => true,
        ];
    }

    private function organizeChallengePhases($phasesData)
    {
        if (empty($phasesData)) {
            return [];
        }
        
        // Se há apenas uma lei, não mostrar módulos
        $uniqueReferences = [];
        foreach ($phasesData as $phase) {
            $uuid = $phase['reference_uuid'];
            if (!isset($uniqueReferences[$uuid])) {
                $uniqueReferences[$uuid] = [
                    'uuid' => $uuid,
                    'name' => $phase['reference_name']
                ];
            }
        }
        
        if (count($uniqueReferences) <= 1) {
            return [];
        }
        
        $modules = [];
        $moduleNumber = 1;
        $totalPhases = count($phasesData);
        $phasesPerModule = 12; // Aproximadamente
        
        for ($startIndex = 0; $startIndex < $totalPhases; $startIndex += $phasesPerModule) {
            $endIndex = min($startIndex + $phasesPerModule, $totalPhases);
            $modulePhasesSlice = array_slice($phasesData, $startIndex, $endIndex - $startIndex);
            
            if (empty($modulePhasesSlice)) {
                continue;
            }
            
            $referenceGroups = [];
            foreach ($modulePhasesSlice as $phase) {
                $uuid = $phase['reference_uuid'];
                if (!isset($referenceGroups[$uuid])) {
                    $referenceGroups[$uuid] = [
                        'reference_name' => $phase['reference_name'],
                        'reference_uuid' => $uuid,
                        'phase_ids' => []
                    ];
                }
                $referenceGroups[$uuid]['phase_ids'][] = $phase['id'];
            }
            
            if (!empty($referenceGroups)) {
                $modules[] = [
                    'id' => $moduleNumber,
                    'title' => "Módulo {$moduleNumber}",
                    'references' => array_values($referenceGroups)
                ];
                $moduleNumber++;
            }
        }
        
        return $modules;
    }

    private function calculateAverageDifficulty($articles)
    {
        if ($articles->isEmpty()) {
            return 1;
        }
        $totalDifficulty = $articles->sum('difficulty_level');
        return round($totalDifficulty / $articles->count());
    }

    private function buildChallengePhaseToArticleMap($challengeArticles)
    {
        // Usar as mesmas constantes do PlayController
        $ARTICLES_PER_PHASE = 6;
        $REVIEW_PHASE_INTERVAL = 3;
        $PHASES_PER_MODULE_PER_LAW = 6;

        // Agrupar artigos por referência legal (mesmo que no map)
        $legalReferencesData = [];
        foreach ($challengeArticles as $article) {
            $refUuid = $article->legalReference->uuid;
            if (!isset($legalReferencesData[$refUuid])) {
                $legalReferencesData[$refUuid] = [
                    'reference' => $article->legalReference,
                    'articles' => collect()
                ];
            }
            $legalReferencesData[$refUuid]['articles']->push($article);
        }

        // Construir estrutura de fases (mesmo que no map)
        $phaseStructureList = [];
        $lawChunksData = [];
        $maxChunks = 0;
        
        foreach ($legalReferencesData as $refUuid => $refData) {
            $chunks = $refData['articles']->chunk($ARTICLES_PER_PHASE);
            $lawChunksData[$refUuid] = [
                'reference' => $refData['reference'],
                'chunks' => $chunks,
                'total_chunks' => $chunks->count()
            ];
            $maxChunks = max($maxChunks, $chunks->count());
        }
        
        // Intercalar fases por módulo (mesmo que no map)
        $tempCounter = 0;
        $totalModules = ceil($maxChunks / $PHASES_PER_MODULE_PER_LAW);
        
        for ($moduleIndex = 0; $moduleIndex < $totalModules; $moduleIndex++) {
            $startChunkIndex = $moduleIndex * $PHASES_PER_MODULE_PER_LAW;
            $endChunkIndex = $startChunkIndex + $PHASES_PER_MODULE_PER_LAW;
            
            foreach ($lawChunksData as $lawUuid => $lawData) {
                $phasesAddedForThisLaw = 0;
                
                for ($chunkIndex = $startChunkIndex; $chunkIndex < $endChunkIndex && $phasesAddedForThisLaw < $PHASES_PER_MODULE_PER_LAW; $chunkIndex++) {
                    if ($chunkIndex < $lawData['total_chunks']) {
                        $tempCounter++;
                        
                        $phaseStructureList[] = [
                            'id' => $tempCounter,
                            'is_review' => false,
                            'reference_uuid' => $lawUuid,
                            'chunk_index' => $chunkIndex,
                            'article_chunk' => $lawData['chunks'][$chunkIndex]
                        ];
                        
                        $phasesAddedForThisLaw++;
                    }
                }
                
                if ($phasesAddedForThisLaw > 0) {
                    $totalRegularPhasesOfThisLaw = min($endChunkIndex, $lawData['total_chunks']);
                    
                    if ($totalRegularPhasesOfThisLaw % $REVIEW_PHASE_INTERVAL === 0) {
                        $tempCounter++;
                        $phaseStructureList[] = [
                            'id' => $tempCounter,
                            'is_review' => true,
                            'reference_uuid' => $lawUuid,
                            'last_regular_counter' => $totalRegularPhasesOfThisLaw
                        ];
                    }
                }
            }
        }

        // Construir mapa: phaseId -> articleIndex
        $phaseToArticleMap = [];
        $articleIndexCounter = 0;
        
        foreach ($phaseStructureList as $phaseStruct) {
            if (!$phaseStruct['is_review'] && isset($phaseStruct['article_chunk'])) {
                foreach ($phaseStruct['article_chunk'] as $article) {
                    $phaseToArticleMap[$phaseStruct['id']] = $articleIndexCounter;
                    $articleIndexCounter++;
                }
            }
        }

        return $phaseToArticleMap;
    }

    private function getChallengePhaseArticles($challenge, $phaseNumber)
    {
        // Usar as mesmas constantes do PlayController
        $ARTICLES_PER_PHASE = 6;
        $REVIEW_PHASE_INTERVAL = 3;
        $PHASES_PER_MODULE_PER_LAW = 6;

        // Obter artigos do desafio
        $challengeArticles = $challenge->getSelectedArticles();
        
        // Agrupar artigos por referência legal (mesmo que no map)
        $legalReferencesData = [];
        foreach ($challengeArticles as $article) {
            $refUuid = $article->legalReference->uuid;
            if (!isset($legalReferencesData[$refUuid])) {
                $legalReferencesData[$refUuid] = [
                    'reference' => $article->legalReference,
                    'articles' => collect()
                ];
            }
            $legalReferencesData[$refUuid]['articles']->push($article);
        }

        // Construir estrutura de fases (mesmo que no map)
        $phaseStructureList = [];
        $lawChunksData = [];
        $maxChunks = 0;
        
        foreach ($legalReferencesData as $refUuid => $refData) {
            $chunks = $refData['articles']->chunk($ARTICLES_PER_PHASE);
            $lawChunksData[$refUuid] = [
                'reference' => $refData['reference'],
                'chunks' => $chunks,
                'total_chunks' => $chunks->count()
            ];
            $maxChunks = max($maxChunks, $chunks->count());
        }
        
        // Intercalar fases por módulo (mesmo que no map)
        $tempCounter = 0;
        $totalModules = ceil($maxChunks / $PHASES_PER_MODULE_PER_LAW);
        
        for ($moduleIndex = 0; $moduleIndex < $totalModules; $moduleIndex++) {
            $startChunkIndex = $moduleIndex * $PHASES_PER_MODULE_PER_LAW;
            $endChunkIndex = $startChunkIndex + $PHASES_PER_MODULE_PER_LAW;
            
            foreach ($lawChunksData as $lawUuid => $lawData) {
                $phasesAddedForThisLaw = 0;
                
                for ($chunkIndex = $startChunkIndex; $chunkIndex < $endChunkIndex && $phasesAddedForThisLaw < $PHASES_PER_MODULE_PER_LAW; $chunkIndex++) {
                    if ($chunkIndex < $lawData['total_chunks']) {
                        $tempCounter++;
                        
                        $phaseStructureList[] = [
                            'id' => $tempCounter,
                            'is_review' => false,
                            'reference_uuid' => $lawUuid,
                            'chunk_index' => $chunkIndex,
                            'article_chunk' => $lawData['chunks'][$chunkIndex]
                        ];
                        
                        $phasesAddedForThisLaw++;
                    }
                }
                
                if ($phasesAddedForThisLaw > 0) {
                    $totalRegularPhasesOfThisLaw = min($endChunkIndex, $lawData['total_chunks']);
                    
                    if ($totalRegularPhasesOfThisLaw % $REVIEW_PHASE_INTERVAL === 0) {
                        $tempCounter++;
                        $phaseStructureList[] = [
                            'id' => $tempCounter,
                            'is_review' => true,
                            'reference_uuid' => $lawUuid,
                            'last_regular_counter' => $totalRegularPhasesOfThisLaw
                        ];
                    }
                }
            }
        }

        // Encontrar a fase específica e retornar informações sobre ela
        foreach ($phaseStructureList as $phaseStruct) {
            if ($phaseStruct['id'] === $phaseNumber) {
                if ($phaseStruct['is_review']) {
                    // Para fases de revisão, retornar apenas artigos que precisam de revisão
                    $articleIdsInScope = $this->getChallengeArticlesInScopeForReview($phaseNumber, $phaseStructureList, $challenge->id);
                    
                    // Converter para array se for Collection
                    if (is_object($articleIdsInScope) && method_exists($articleIdsInScope, 'toArray')) {
                        $articleIdsInScope = $articleIdsInScope->toArray();
                    }
                    
                    // Filtrar apenas artigos que precisam de revisão (< 100% OU nunca tentados)
                    $userId = Auth::id();
                    
                    // Buscar artigos com progresso < 100%
                    $articlesNeedingReview = ChallengeProgress::where('user_id', $userId)
                        ->where('challenge_id', $challenge->id)
                        ->whereIn('law_article_id', $articleIdsInScope)
                        ->where('percentage', '<', 100)
                        ->pluck('law_article_id');
                    
                    // Buscar artigos nunca tentados (sem progresso)
                    $articlesNeverAttempted = collect($articleIdsInScope)->diff(
                        ChallengeProgress::where('user_id', $userId)
                            ->where('challenge_id', $challenge->id)
                            ->whereIn('law_article_id', $articleIdsInScope)
                            ->pluck('law_article_id')
                    );
                    
                    // Unir os dois grupos
                    $allArticlesToReview = $articlesNeedingReview->concat($articlesNeverAttempted)->unique();
                    
                    return collect($challengeArticles)->filter(function($article) use ($allArticlesToReview) {
                        return $allArticlesToReview->contains($article->id);
                    });
                } else {
                    // Para fases regulares, retornar o chunk de artigos
                    return $phaseStruct['article_chunk'];
                }
            }
        }

        return collect(); // Retorna collection vazia se não encontrar
    }

    private function getChallengePhaseInfo($challenge, $phaseNumber)
    {
        // Usar as mesmas constantes do PlayController
        $ARTICLES_PER_PHASE = 6;
        $REVIEW_PHASE_INTERVAL = 3;
        $PHASES_PER_MODULE_PER_LAW = 6;

        // Obter artigos do desafio
        $challengeArticles = $challenge->getSelectedArticles();
        
        // Agrupar artigos por referência legal (mesmo que no map)
        $legalReferencesData = [];
        foreach ($challengeArticles as $article) {
            $refUuid = $article->legalReference->uuid;
            if (!isset($legalReferencesData[$refUuid])) {
                $legalReferencesData[$refUuid] = [
                    'reference' => $article->legalReference,
                    'articles' => collect()
                ];
            }
            $legalReferencesData[$refUuid]['articles']->push($article);
        }

        // Construir estrutura de fases (mesmo que no map)
        $phaseStructureList = [];
        $lawChunksData = [];
        $maxChunks = 0;
        
        foreach ($legalReferencesData as $refUuid => $refData) {
            $chunks = $refData['articles']->chunk($ARTICLES_PER_PHASE);
            $lawChunksData[$refUuid] = [
                'reference' => $refData['reference'],
                'chunks' => $chunks,
                'total_chunks' => $chunks->count()
            ];
            $maxChunks = max($maxChunks, $chunks->count());
        }
        
        // Intercalar fases por módulo (mesmo que no map)
        $tempCounter = 0;
        $totalModules = ceil($maxChunks / $PHASES_PER_MODULE_PER_LAW);
        
        for ($moduleIndex = 0; $moduleIndex < $totalModules; $moduleIndex++) {
            $startChunkIndex = $moduleIndex * $PHASES_PER_MODULE_PER_LAW;
            $endChunkIndex = $startChunkIndex + $PHASES_PER_MODULE_PER_LAW;
            
            foreach ($lawChunksData as $lawUuid => $lawData) {
                $phasesAddedForThisLaw = 0;
                
                for ($chunkIndex = $startChunkIndex; $chunkIndex < $endChunkIndex && $phasesAddedForThisLaw < $PHASES_PER_MODULE_PER_LAW; $chunkIndex++) {
                    if ($chunkIndex < $lawData['total_chunks']) {
                        $tempCounter++;
                        
                        $phaseStructureList[] = [
                            'id' => $tempCounter,
                            'is_review' => false,
                            'reference_uuid' => $lawUuid,
                            'chunk_index' => $chunkIndex,
                            'article_chunk' => $lawData['chunks'][$chunkIndex]
                        ];
                        
                        $phasesAddedForThisLaw++;
                    }
                }
                
                if ($phasesAddedForThisLaw > 0) {
                    $totalRegularPhasesOfThisLaw = min($endChunkIndex, $lawData['total_chunks']);
                    
                    if ($totalRegularPhasesOfThisLaw % $REVIEW_PHASE_INTERVAL === 0) {
                        $tempCounter++;
                        $phaseStructureList[] = [
                            'id' => $tempCounter,
                            'is_review' => true,
                            'reference_uuid' => $lawUuid,
                            'last_regular_counter' => $totalRegularPhasesOfThisLaw
                        ];
                    }
                }
            }
        }

        // Encontrar a fase específica e retornar suas informações
        foreach ($phaseStructureList as $phaseStruct) {
            if ($phaseStruct['id'] === $phaseNumber) {
                return $phaseStruct;
            }
        }

        return null; // Retorna null se não encontrar
    }

    private function hasNextChallengePhase($challenge, $currentPhaseNumber)
    {
        // Verificar se existe uma fase posterior usando getChallengePhaseInfo
        $phaseInfo = $this->getChallengePhaseInfo($challenge, $currentPhaseNumber + 1);
        return $phaseInfo !== null;
    }

    private function getNextChallengePhaseNumber($challenge, $currentPhaseNumber)
    {
        // Para desafios, simplesmente incrementa o número da fase
        return $currentPhaseNumber + 1;
    }

    // Buscar usuários que estão em cada fase do desafio
    private function getChallengeUsersPerPhase($challenge)
    {
        // Buscar todos os usuários que têm progresso neste desafio
        $challengeProgress = ChallengeProgress::where('challenge_id', $challenge->id)
            ->with(['user' => function($query) {
                $query->select('id', 'name');
            }])
            ->get();

        // Obter artigos do desafio organizados por fase
        $challengeArticles = $challenge->getSelectedArticles();
        
        // Usar as mesmas constantes para calcular fases
        $ARTICLES_PER_PHASE = 6;
        $REVIEW_PHASE_INTERVAL = 3;
        $PHASES_PER_MODULE_PER_LAW = 6;

        // Agrupar artigos por referência legal (mesma lógica do map)
        $legalReferencesData = [];
        foreach ($challengeArticles as $article) {
            $refUuid = $article->legalReference->uuid;
            if (!isset($legalReferencesData[$refUuid])) {
                $legalReferencesData[$refUuid] = [
                    'reference' => $article->legalReference,
                    'articles' => collect()
                ];
            }
            $legalReferencesData[$refUuid]['articles']->push($article);
        }

        // Construir estrutura de fases (mesma lógica do map)
        $phaseStructureList = [];
        $lawChunksData = [];
        $maxChunks = 0;
        
        foreach ($legalReferencesData as $refUuid => $refData) {
            $chunks = $refData['articles']->chunk($ARTICLES_PER_PHASE);
            $lawChunksData[$refUuid] = [
                'reference' => $refData['reference'],
                'chunks' => $chunks,
                'total_chunks' => $chunks->count()
            ];
            $maxChunks = max($maxChunks, $chunks->count());
        }
        
        // Intercalar fases por módulo (mesma lógica do map)
        $tempCounter = 0;
        $totalModules = ceil($maxChunks / $PHASES_PER_MODULE_PER_LAW);
        
        for ($moduleIndex = 0; $moduleIndex < $totalModules; $moduleIndex++) {
            $startChunkIndex = $moduleIndex * $PHASES_PER_MODULE_PER_LAW;
            $endChunkIndex = $startChunkIndex + $PHASES_PER_MODULE_PER_LAW;
            
            foreach ($lawChunksData as $lawUuid => $lawData) {
                $phasesAddedForThisLaw = 0;
                
                for ($chunkIndex = $startChunkIndex; $chunkIndex < $endChunkIndex && $phasesAddedForThisLaw < $PHASES_PER_MODULE_PER_LAW; $chunkIndex++) {
                    if ($chunkIndex < $lawData['total_chunks']) {
                        $tempCounter++;
                        
                        $phaseStructureList[] = [
                            'id' => $tempCounter,
                            'is_review' => false,
                            'reference_uuid' => $lawUuid,
                            'chunk_index' => $chunkIndex,
                            'article_chunk' => $lawData['chunks'][$chunkIndex]
                        ];
                        
                        $phasesAddedForThisLaw++;
                    }
                }
                
                if ($phasesAddedForThisLaw > 0) {
                    $totalRegularPhasesOfThisLaw = min($endChunkIndex, $lawData['total_chunks']);
                    
                    if ($totalRegularPhasesOfThisLaw % $REVIEW_PHASE_INTERVAL === 0) {
                        $tempCounter++;
                        $phaseStructureList[] = [
                            'id' => $tempCounter,
                            'is_review' => true,
                            'reference_uuid' => $lawUuid,
                            'last_regular_counter' => $totalRegularPhasesOfThisLaw
                        ];
                    }
                }
            }
        }

        // Mapear artigos para fases
        $articleToPhaseMap = [];
        foreach ($phaseStructureList as $phaseStruct) {
            if (!$phaseStruct['is_review'] && isset($phaseStruct['article_chunk'])) {
                foreach ($phaseStruct['article_chunk'] as $article) {
                    $articleToPhaseMap[$article->id] = $phaseStruct['id'];
                }
            }
        }

        // Determinar fase atual de cada usuário
        $usersPerPhase = [];
        
        foreach ($challengeProgress->groupBy('user_id') as $userId => $userProgress) {
            $user = $userProgress->first()->user;
            if (!$user) continue;

            // Mapear progresso por fase
            $progressByPhase = [];
            foreach ($userProgress as $progress) {
                $articleId = $progress->law_article_id;
                $phaseId = $articleToPhaseMap[$articleId] ?? null;
                
                if ($phaseId) {
                    if (!isset($progressByPhase[$phaseId])) {
                        $progressByPhase[$phaseId] = [
                            'completed_articles' => 0,
                            'attempted_articles' => 0,
                            'total_articles' => 0
                        ];
                    }
                    
                    $progressByPhase[$phaseId]['total_articles']++;
                    
                    if ($progress->attempts > 0) {
                        $progressByPhase[$phaseId]['attempted_articles']++;
                    }
                    
                    if ($progress->is_completed) {
                        $progressByPhase[$phaseId]['completed_articles']++;
                    }
                }
            }
            
            // Determinar fase atual usando a MESMA lógica do map() principal
            $currentPhase = null;
            $blockSubsequent = false;
            $previousPhaseIsComplete = true;
            $currentLawUuid = null;
            $lawCompletionStatus = [];
            
            // Percorrer fases exatamente como no método map()
            foreach ($phaseStructureList as $phaseStruct) {
                $phaseId = $phaseStruct['id'];
                
                // Lógica de mudança de lei (igual ao map)
                if ($phaseStruct['reference_uuid'] !== $currentLawUuid) {
                    $previousLawUuid = $currentLawUuid;
                    $currentLawUuid = $phaseStruct['reference_uuid'];
                    
                    if ($previousLawUuid !== null && isset($lawCompletionStatus[$previousLawUuid]) && !$lawCompletionStatus[$previousLawUuid]) {
                        $blockSubsequent = true;
                    }
                    $lawCompletionStatus[$currentLawUuid] = true;
                }

                $isPhaseBlocked = $blockSubsequent || !$previousPhaseIsComplete;
                $isPhaseComplete = false;
                
                if ($phaseStruct['is_review']) {
                    // Para revisão, verificar se há artigos que precisam revisar
                    $phaseProgress = $progressByPhase[$phaseId] ?? null;
                    if ($phaseProgress) {
                        // Considerar completa se todos os artigos da fase foram tentados com sucesso
                        $isPhaseComplete = ($phaseProgress['completed_articles'] === $phaseProgress['total_articles']);
                    } else {
                        $isPhaseComplete = true; // Sem progresso = não há nada para revisar
                    }
                } else {
                    // Para fase regular, usar EXATAMENTE a mesma lógica do método map()
                    $phaseProgress = $progressByPhase[$phaseId] ?? null;
                    if ($phaseProgress) {
                        // Recalcular usando a mesma função getChallengePhaseProgress
                        $articleChunk = $phaseStruct['article_chunk'] ?? collect();
                        $realPhaseProgress = $this->getChallengePhaseProgress($userId, $challenge->id, $articleChunk);
                        $isPhaseComplete = $realPhaseProgress['all_attempted'];
                    } else {
                        $isPhaseComplete = false;
                    }
                }

                // Determinar se esta é a fase atual (igual ao map)
                if (!$isPhaseBlocked && !$isPhaseComplete && $currentPhase === null) {
                    $currentPhase = $phaseId;
                    $blockSubsequent = true;
                }

                if (!$isPhaseComplete) {
                    $lawCompletionStatus[$currentLawUuid] = false;
                }

                $previousPhaseIsComplete = $isPhaseComplete;
            }
            
            // Se não encontrou fase atual, significa que completou tudo
            if (!$currentPhase) {
                continue;
            }
            
            // Adicionar usuário à fase atual
            if (!isset($usersPerPhase[$currentPhase])) {
                $usersPerPhase[$currentPhase] = [];
            }
            
            $usersPerPhase[$currentPhase][] = [
                'id' => $user->id,
                'name' => $user->name
            ];
        }

        return $usersPerPhase;
    }
}