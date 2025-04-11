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
use Illuminate\Support\Collection; // Adicionar para tipagem

class PlayController extends Controller
{
    const ARTICLES_PER_PHASE = 7;
    const REVIEW_PHASE_INTERVAL = 2;


    public function map()
    {
        $user = Auth::user();
        $userId = $user->id;

        if (!$user->hasLives() && !$user->hasActiveSubscription()) {
            return redirect()->route('play.nolives');
        }

        $hasPreferences = $user->legalReferences()->exists();

        $legalReferencesQuery = LegalReference::with(['articles' => function($query) {
            $query->orderBy('position', 'asc')->where('is_active', true);
        }]);

        if ($hasPreferences) {
            $legalReferencesQuery->whereHas('users', function($query) use ($userId) {
                $query->where('users.id', $userId);
            });
        } else {
             if (!LegalReference::exists()) {
                 return redirect()->route('dashboard')->with('message', 'Nenhuma lei disponível no momento.');
             }
             $legalReferencesQuery = LegalReference::with(['articles' => function($query) {
                 $query->orderBy('position', 'asc')->where('is_active', true);
             }])->where('is_active', true);
        }

        $legalReferences = $legalReferencesQuery->orderBy('id', 'asc')->get();

        if ($legalReferences->isEmpty()) {
             return redirect()->route('user.legal-references.index')
                    ->with('message', $hasPreferences ? 'Nenhuma das suas leis selecionadas está disponível ou ativa.' : 'Selecione as leis que deseja estudar.');
        }

        // --- Lógica de Geração de Fases e Bloqueio (Revisada) ---

        $phasesData = []; // Array final com dados completos das fases
        $phaseStructureList = []; // Lista apenas com a estrutura (ID, tipo, ref, chunk) para lógica de escopo
        $globalPhaseCounter = 0;
        $currentPhaseId = null; // ID da fase que será marcada como 'is_current'
        $blockSubsequent = false; // Flag para bloquear todas as fases após a atual ser encontrada
        $previousPhaseIsComplete = true; // Estado da fase *anterior* (true se concluída no novo sentido)
        $currentLawUuid = null;
        $lawCompletionStatus = []; // uuid => bool (true se lei 100% completa no novo sentido)

        // --- PASSO 1: Construir a ESTRUTURA completa de fases ---
        // Isso é necessário para que getArticlesInScopeForReview funcione corretamente
        $tempCounter = 0;
        foreach ($legalReferences as $reference) {
             $chunks = $reference->articles->chunk(self::ARTICLES_PER_PHASE);
             $regularPhaseCounter = 0;
             foreach ($chunks as $chunkIndex => $articleChunk) {
                 $regularPhaseCounter++;
                 $tempCounter++;
                 $phaseStructureList[] = [
                     'id' => $tempCounter,
                     'is_review' => false,
                     'reference_uuid' => $reference->uuid,
                     'chunk_index' => $chunkIndex,
                     'article_chunk' => $articleChunk // Armazena temporariamente para Pass 2
                 ];
                 if ($regularPhaseCounter % self::REVIEW_PHASE_INTERVAL === 0) {
                     $tempCounter++;
                     $phaseStructureList[] = [
                         'id' => $tempCounter,
                         'is_review' => true,
                         'reference_uuid' => $reference->uuid,
                         'last_regular_counter' => $regularPhaseCounter // Para título da revisão
                     ];
                 }
             }
        }
        // Log::debug("[Map Structure] Phase structure list built. Count: " . count($phaseStructureList));


        // --- PASSO 2: Iterar pela ESTRUTURA para calcular progresso, bloqueios e fase atual ---
        foreach ($phaseStructureList as $phaseIndex => $phaseStruct) {
            $currentPhaseGlobalId = $phaseStruct['id']; // ID global desta fase

             // Verificar se a lei mudou para avaliar bloqueio inter-lei
             if ($phaseStruct['reference_uuid'] !== $currentLawUuid) {
                 $previousLawUuid = $currentLawUuid;
                 $currentLawUuid = $phaseStruct['reference_uuid'];
                 // Log::debug("[Map Pass 2 - Phase {$currentPhaseGlobalId}] Switched to Law UUID: {$currentLawUuid}");

                 // Verificar se a lei anterior *não* foi completada (se houver lei anterior)
                 if ($previousLawUuid !== null && isset($lawCompletionStatus[$previousLawUuid]) && !$lawCompletionStatus[$previousLawUuid]) {
                     // Log::debug("[Map Pass 2 - Phase {$currentPhaseGlobalId}] Blocking subsequent phases because previous law ({$previousLawUuid}) was incomplete.");
                     $blockSubsequent = true; // Bloqueia esta lei e as seguintes
                 }
                  // Assumir que a lei atual está completa até prova em contrário
                  $lawCompletionStatus[$currentLawUuid] = true;
             }

             // --- Lógica central de bloqueio e conclusão ---
             // $isPhaseBlocked depende do estado da fase ANTERIOR ($previousPhaseIsComplete)
             // e se o bloqueio geral ($blockSubsequent) já foi ativado.
             $isPhaseBlocked = $blockSubsequent || !$previousPhaseIsComplete;
             $isPhaseCurrent = false; // Será definida como true se for a primeira incompleta e não bloqueada
             $phaseProgress = null;
             $isPhaseComplete = false; // Estado de conclusão DESTA fase (no novo sentido)
             $phaseBuiltData = null; // Dados finais a serem adicionados a $phasesData

             // Calcular progresso e conclusão específica para tipo de fase
             if ($phaseStruct['is_review']) {
                 // --- Fase de Revisão ---
                 // Log::debug("[Map Pass 2 - Phase {$currentPhaseGlobalId}] Processing REVIEW phase.");
                 $articleIdsInScope = $this->getArticlesInScopeForReview($currentPhaseGlobalId, $phaseStructureList); // Passa a lista de ESTRUTURA completa
                 $phaseProgress = $this->getReviewPhaseProgress($userId, $articleIdsInScope);
                 $isPhaseComplete = $phaseProgress['is_complete']; // Revisão completa se !needs_review

                 // Log::debug("[Map Pass 2 - Phase {$currentPhaseGlobalId}] Review - Blocked: ".($isPhaseBlocked?'Yes':'No').", Complete: ".($isPhaseComplete?'Yes':'No'));

                 // Determina se é a fase atual GLOBAL
                 if (!$isPhaseBlocked && !$isPhaseComplete && $currentPhaseId === null) {
                     $isPhaseCurrent = true;
                     $currentPhaseId = $currentPhaseGlobalId;
                      // Log::debug("[Map Pass 2 - Phase {$currentPhaseGlobalId}] SET AS CURRENT (Review). Blocking subsequent.");
                 }

                 // Construir dados finais da fase de revisão
                 $phaseBuiltData = $this->buildReviewPhaseData(
                    $currentPhaseGlobalId,
                    $legalReferences->firstWhere('uuid', $currentLawUuid), // Obter o modelo da referência
                    $phaseStruct['last_regular_counter'],
                    $isPhaseBlocked,
                    $isPhaseCurrent,
                    $phaseProgress
                 );

             } else {
                 // --- Fase Regular ---
                 Log::debug("[Map Pass 2 - Phase {$currentPhaseGlobalId}] Processing REGULAR phase (Chunk {$phaseStruct['chunk_index']}).");
                 $phaseProgress = $this->getPhaseProgress($userId, $phaseStruct['article_chunk']);
                 $isPhaseComplete = $phaseProgress['all_attempted']; // Regular completa se all_attempted

                 Log::debug("[Map Pass 2 - Phase {$currentPhaseGlobalId}] Regular - Blocked: ".($isPhaseBlocked?'Yes':'No').", Attempted(Complete): ".($isPhaseComplete?'Yes':'No'));

                 // Determina se é a fase atual GLOBAL
                 if (!$isPhaseBlocked && !$isPhaseComplete && $currentPhaseId === null) {
                     $isPhaseCurrent = true;
                     $currentPhaseId = $currentPhaseGlobalId;
                     Log::debug("[Map Pass 2 - Phase {$currentPhaseGlobalId}] SET AS CURRENT (Regular). Blocking subsequent.");
                 }

                 // Construir dados finais da fase regular
                 $phaseBuiltData = $this->buildPhaseData(
                    $currentPhaseGlobalId,
                    $legalReferences->firstWhere('uuid', $currentLawUuid), // Obter o modelo da referência
                    $phaseStruct['article_chunk'],
                    $phaseStruct['chunk_index'],
                    $phaseProgress,
                    $isPhaseBlocked,
                    $isPhaseCurrent
                 );
             }

             // Atualizar status de conclusão da LEI se esta fase for incompleta
             if (!$isPhaseComplete) {
                  if ($lawCompletionStatus[$currentLawUuid]) { 
                     // Log::debug("[Map Pass 2 - Phase {$currentPhaseGlobalId}] Marking Law UUID {$currentLawUuid} as incomplete due to this phase.");
                 }
                 $lawCompletionStatus[$currentLawUuid] = false;
             }

             // Adicionar dados construídos ao array final
             if ($phaseBuiltData) {
                 $phasesData[] = $phaseBuiltData;
             } else {
                  // Log::error("[Map Pass 2 - Phase {$currentPhaseGlobalId}] Failed to build phase data.");
             }


             // --- Atualizar estado para a PRÓXIMA iteração ---
             // O estado de conclusão DESTA fase determina se a PRÓXIMA estará bloqueada
             $previousPhaseIsComplete = $isPhaseComplete;

             // Ativar o bloqueio subsequente se esta fase foi marcada como a atual
             // Isso garante que mesmo que a próxima fase na lista seja calculada como "completa",
             // ela ainda será bloqueada porque $blockSubsequent será true.
             if ($isPhaseCurrent) {
                 $blockSubsequent = true;
             }

        } // Fim do loop PASSO 2

        Log::debug("[Map Completion] Finished Pass 2. CurrentPhaseId identified: {$currentPhaseId}.");


        // --- PASSO 3: Ajuste Final - Garantir que is_current e is_blocked estejam corretos ---
        // A fase atual NUNCA deve ser bloqueada.
        if ($currentPhaseId !== null) {
            $foundCurrent = false;
            foreach ($phasesData as $index => $phase) {
                 if ($phase['id'] === $currentPhaseId) {
                     // Se esta é a fase atual identificada, garantir que is_current=true e is_blocked=false
                     $phasesData[$index]['is_current'] = true;
                     if ($phasesData[$index]['is_blocked']) {
                         Log::warning("[Map Final Check] Forcing is_blocked=false for identified current phase {$currentPhaseId}.");
                         $phasesData[$index]['is_blocked'] = false;
                     }
                     $foundCurrent = true;
                 } elseif ($phase['is_current']) {
                      // Desmarcar qualquer outra fase que possa ter sido erroneamente marcada como atual
                      Log::warning("[Map Final Check] Found phase {$phase['id']} marked as current, but actual current is {$currentPhaseId}. Unsetting.");
                      $phasesData[$index]['is_current'] = false;
                 }
            }
             if (!$foundCurrent) {
                 Log::error("[Map Final Check] Could not find the identified current phase ID {$currentPhaseId} in the final phasesData array.");
             }
        } else {
             Log::debug("[Map Final Check] No current phase ID identified (likely all phases completed or blocked).");
             // Opcional: Encontrar a última fase não bloqueada como "atual" visualmente? Ou deixar sem fase atual.
             // Por enquanto, deixamos sem fase atual se $currentPhaseId for null.
        }

        
        return Inertia::render('Play/Map', [
            'phases' => $phasesData,
             'user' => [
                'lives' => $user->lives,
                'has_infinite_lives' => $user->hasInfiniteLives()
            ],
        ]);
    } // --- Fim da função map() ---

    // ===============================================================
    // Demais funções (Helpers, phase, review, saveProgress etc.)
    // Certifique-se que as versões mais recentes de
    // buildPhaseData, buildReviewPhaseData, getPhaseProgress,
    // getReviewPhaseProgress, getArticlesInScopeForReview
    // estejam presentes aqui.
    // ===============================================================

     // Helper para construir dados da fase regular
    private function buildPhaseData(int $phaseId, LegalReference $reference, Collection $articleChunk, int $chunkIndex, array $progress, bool $isBlocked, bool $isCurrent): array
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
            'is_complete' => $progress['all_attempted'] ?? false, // Fase completa se todos foram tentados
            'progress' => $progress,
            'is_blocked' => $isBlocked,
            'is_current' => $isCurrent,
            'is_review' => false,
            'chunk_index' => $chunkIndex,
        ];
    }

    // Helper para construir dados da fase de revisão
    private function buildReviewPhaseData(int $phaseId, LegalReference $reference, int $lastRegularPhaseCounter, bool $isBlocked, bool $isCurrent, ?array $progress = null): array
    {
        if ($progress === null) {
             $progress = [
                 'completed' => 0, 'total' => 0, 'percentage' => 0, 'article_status' => [],
                 'is_fully_complete' => false, 'all_attempted' => false,
                 'is_complete' => true, // Assumir completa se bloqueada/sem dados
                 'needs_review' => false ];
        }

        return [
            'id' => $phaseId,
            'title' => 'Revisão ' . ceil($lastRegularPhaseCounter / self::REVIEW_PHASE_INTERVAL),
            'reference_name' => $reference->name,
            'reference_uuid' => $reference->uuid,
            'article_count' => $progress['articles_to_review_count'] ?? 0,
            'difficulty' => 3,
            'first_article' => null,
            'phase_number' => $phaseId,
            'is_complete' => $progress['is_complete'] ?? true, // Completa se !needs_review
            'progress' => $progress,
            'is_blocked' => $isBlocked,
            'is_current' => $isCurrent,
            'is_review' => true,
        ];
    }

    // getPhaseProgress
     private function getPhaseProgress($userId, Collection $articles)
    {
        if (!$userId || $articles->isEmpty()) {
            return [
                'completed' => 0, 'total' => $articles->count(), 'percentage' => 0,
                'article_status' => array_fill(0, $articles->count(), 'pending'),
                'is_fully_complete' => false, 'all_attempted' => false ];
        }
        $articleIds = $articles->pluck('id');
        $progressRecords = UserProgress::where('user_id', $userId)
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

    // getReviewPhaseProgress (aceita coleção de IDs)
    private function getReviewPhaseProgress($userId, Collection $articleIdsInScope)
    {
        if (!$userId || $articleIdsInScope->isEmpty()) {
            //  Log::debug("[ReviewProgress User {$userId}] No articles in scope.");
            return [
                'is_complete' => true, 'needs_review' => false, 'articles_to_review_count' => 0,
                'completed' => 0, 'total' => 0, 'percentage' => 0, 'article_status' => [],
                'is_fully_complete' => true, 'all_attempted' => true ];
        }

        $incompleteArticlesCount = UserProgress::where('user_id', $userId)
            ->whereIn('law_article_id', $articleIdsInScope)
            ->where('percentage', '<', 100)
            ->count();

        //  Log::debug("[ReviewProgress User {$userId}] Scope IDs: [".$articleIdsInScope->implode(', ')."]. Incomplete count: {$incompleteArticlesCount}.");

        $needsReview = $incompleteArticlesCount > 0;

        return [
            'is_complete' => !$needsReview, 'needs_review' => $needsReview,
            'articles_to_review_count' => $incompleteArticlesCount,
            'completed' => 0, 'total' => $incompleteArticlesCount, 'percentage' => 0, 'article_status' => [],
            'is_fully_complete' => !$needsReview, 'all_attempted' => !$needsReview ];
    }

    // getArticlesInScopeForReview (aceita lista de estrutura, retorna coleção de IDs)
    private function getArticlesInScopeForReview(int $reviewPhaseId, array $fullPhaseStructureList): Collection
    {
        $articlesInScope = collect();
        $referenceUuid = null;
        $startIndex = -1;
        $endIndex = -1;
        $currentReviewPhaseIndex = -1;

        // 1. Find target review phase
        foreach($fullPhaseStructureList as $index => $phaseStruct) {
            if ($phaseStruct['id'] === $reviewPhaseId && $phaseStruct['is_review']) {
                $referenceUuid = $phaseStruct['reference_uuid'];
                $currentReviewPhaseIndex = $index;
                break;
            }
        }
        if ($currentReviewPhaseIndex === -1 || $referenceUuid === null) {
            // Log::debug("[ScopeReview {$reviewPhaseId}] Review phase not found in structure list.");
            return collect();
        }
        // Log::debug("[ScopeReview {$reviewPhaseId}] Found review phase at index {$currentReviewPhaseIndex}.");

        // 2. Find start index
        $startIndex = 0;
        $foundBoundary = false;
        for ($i = $currentReviewPhaseIndex - 1; $i >= 0; $i--) {
            $phase = $fullPhaseStructureList[$i];
            if ($phase['reference_uuid'] !== $referenceUuid) {
                $startIndex = $i + 1;
                $foundBoundary = true;
                // Log::debug("[ScopeReview {$reviewPhaseId}] Boundary: Different law at index {$i}. StartIndex set to {$startIndex}.");
                break;
            }
            if ($phase['is_review']) {
                $startIndex = $i + 1;
                $foundBoundary = true;
                //  Log::debug("[ScopeReview {$reviewPhaseId}] Boundary: Previous review at index {$i}. StartIndex set to {$startIndex}.");
                break;
            }
        }
        if (!$foundBoundary) {
             foreach ($fullPhaseStructureList as $idx => $p) {
                 if ($p['reference_uuid'] === $referenceUuid) {
                     $startIndex = $idx;
                    //  Log::debug("[ScopeReview {$reviewPhaseId}] No boundary looping back. First phase of law found at index {$startIndex}.");
                     break;
                 }
             }
        }

        // 3. End index
        $endIndex = $currentReviewPhaseIndex - 1;
        //  Log::debug("[ScopeReview {$reviewPhaseId}] EndIndex set to {$endIndex}.");

        // 4. Collect article IDs
        $articleIds = collect();
        if ($startIndex <= $endIndex) {
            //  Log::debug("[ScopeReview {$reviewPhaseId}] Collecting article IDs from index {$startIndex} to {$endIndex}.");
            static $referenceArticleCache = []; // Static cache within the request
            if (!isset($referenceArticleCache[$referenceUuid])) {
                $refModel = LegalReference::with(['articles' => fn($q)=>$q->orderBy('position')])->where('uuid', $referenceUuid)->first();
                $referenceArticleCache[$referenceUuid] = $refModel ? $refModel->articles : collect();
                //  Log::debug("[ScopeReview {$reviewPhaseId}] Fetched/Cached articles for ref {$referenceUuid}. Count: " . $referenceArticleCache[$referenceUuid]->count());
            }
            $allArticlesOfRef = $referenceArticleCache[$referenceUuid];

            if ($allArticlesOfRef->isEmpty()) {
                Log::warning("[ScopeReview {$reviewPhaseId}] No articles found for ref {$referenceUuid}.");
                return collect();
            }

            $chunks = $allArticlesOfRef->chunk(self::ARTICLES_PER_PHASE);

            for ($i = $startIndex; $i <= $endIndex; $i++) {
                 if (!isset($fullPhaseStructureList[$i])) {
                    //  Log::warning("[ScopeReview {$reviewPhaseId}] Index {$i} out of bounds for structure list.");
                     continue;
                 }
                $phase = $fullPhaseStructureList[$i];
                if (!$phase['is_review'] && isset($phase['chunk_index'])) {
                    $chunkIndex = $phase['chunk_index'];
                    if (isset($chunks[$chunkIndex])) {
                        $chunkArticleIds = $chunks[$chunkIndex]->pluck('id');
                        //  Log::debug("[ScopeReview {$reviewPhaseId}] Adding articles from phase ID {$phase['id']} (Chunk {$chunkIndex}): " . $chunkArticleIds->implode(', '));
                        $articleIds = $articleIds->merge($chunkArticleIds);
                    } else {
                        // Log::warning("[ScopeReview {$reviewPhaseId}] Chunk index {$chunkIndex} not found for phase ID {$phase['id']}.");
                    }
                }
            }
        } else {
            //  Log::debug("[ScopeReview {$reviewPhaseId}] No regular phases in scope (startIndex {$startIndex} > endIndex {$endIndex}).");
        }

        $uniqueArticleIds = $articleIds->unique();
        //  Log::debug("[ScopeReview {$reviewPhaseId}] Total unique article IDs in scope: " . $uniqueArticleIds->count() . " [". $uniqueArticleIds->implode(', ') . "]");

        // 5. Return collection of IDs
        return $uniqueArticleIds;
    }


     // --- Métodos das rotas phase, review, findPhaseDetailsById, etc. ---
     // (Coloque as versões mais recentes dessas funções aqui, garantindo
     // que findPhaseDetailsById replique a lógica de 2 passos do map,
     // e que phase/review chamem findPhaseDetailsById corretamente)

     public function phase($phaseId)
     {
         $user = Auth::user();
         $userId = $user->id;
         $phaseId = (int)$phaseId;

         if (!$user->hasLives() && !$user->hasActiveSubscription()) {
             return redirect()->route('play.nolives');
         }

         list($phaseDetails, $allPhasesList) = $this->findPhaseDetailsById($userId, $phaseId);

         if (!$phaseDetails) {
            //  Log::warning("[Phase Route] Fase não encontrada para ID global: " . $phaseId);
             return redirect()->route('play.map')->with('message', 'Fase não encontrada.');
         }

         // Verificar bloqueio (segurança extra)
          if ($phaseDetails['is_blocked'] && !$phaseDetails['is_current']) {
            //    Log::warning("[Phase Route] Acesso bloqueado à fase: " . $phaseId . " por usuário: " . $userId);
               return redirect()->route('play.map')->with('message', 'Esta fase está bloqueada.');
          }


         // Redirecionar para review se for o caso (deveria ser pego pela rota, mas como fallback)
         if ($phaseDetails['is_review']) {
            //   Log::warning("[Phase Route] Tentativa de acessar fase de revisão ID {$phaseId} pela rota de fase regular. Redirecionando.");
             return redirect()->route('play.review', [
                 'referenceUuid' => $phaseDetails['reference_uuid'],
                 'phase' => $phaseId // Passa ID global
             ]);
         }

         // --- Lógica para Fase Regular ---
         $reference = LegalReference::where('uuid', $phaseDetails['reference_uuid'])->firstOrFail();
         $allArticles = $reference->articles()
             ->orderBy('position', 'asc')
             ->where('is_active', true)
             ->get();
         $chunkedArticles = $allArticles->chunk(self::ARTICLES_PER_PHASE);
         $chunkIndex = $phaseDetails['chunk_index'];

         if (!isset($chunkedArticles[$chunkIndex]) || $chunkedArticles[$chunkIndex]->isEmpty()) {
            //   Log::error("[Phase Route] Chunk de artigos não encontrado para fase regular: " . $phaseId . " chunk: " . $chunkIndex);
             return redirect()->route('play.map')->with('message', 'Erro ao carregar artigos da fase.');
         }

         $phaseArticles = $chunkedArticles[$chunkIndex];
         $articlesWithProgress = $this->getArticlesWithProgress($userId, $phaseArticles);
         $nextPhaseDetails = $this->findNextPhase($phaseId, $allPhasesList);
         
         return Inertia::render('Play/Phase', [
             'phase' => [
                 'title' => $phaseDetails['title'], // Título já formatado
                 'reference_name' => $reference->name,
                 'phase_number' => $phaseId, // ID Global
                 'difficulty' => $this->calculateAverageDifficulty($phaseArticles),
                 'progress' => $phaseDetails['progress'],
                 'has_next_phase' => !!$nextPhaseDetails,
                 'next_phase_number' => $nextPhaseDetails ? $nextPhaseDetails['id'] : null,
                 'next_phase_is_review' => $nextPhaseDetails ? $nextPhaseDetails['is_review'] : false,
                 'reference_uuid' => $phaseDetails['reference_uuid'],
                 'is_review' => false,
             ],
             'articles' => $articlesWithProgress
         ]);
     }

      public function review($referenceUuid, $phaseId) // Renomeado para phaseId
     {
         $phaseId = (int)$phaseId; // ID Global
         $user = Auth::user();
         $userId = $user->id;

         if (!$user->hasLives() && !$user->hasActiveSubscription()) {
             return redirect()->route('play.nolives');
         }

         // Calcular detalhes da fase e lista completa
         list($phaseDetails, $allPhasesList) = $this->findPhaseDetailsById($userId, $phaseId);

          if (!$phaseDetails || !$phaseDetails['is_review'] || $phaseDetails['reference_uuid'] !== $referenceUuid) {
            //    Log::warning("[Review Route] Tentativa de acessar revisão inválida, não encontrada ou UUID não corresponde: Phase ID {$phaseId}, Ref UUID {$referenceUuid}");
               return redirect()->route('play.map')->with('message', 'Fase de revisão não encontrada ou inválida.');
           }
           // Verificar bloqueio
           if ($phaseDetails['is_blocked'] && !$phaseDetails['is_current']) {
            //    Log::warning("[Review Route] Acesso bloqueado à fase de revisão: " . $phaseId . " por usuário: " . $userId);
               return redirect()->route('play.map')->with('message', 'Esta fase está bloqueada.');
           }

         $reference = LegalReference::where('uuid', $referenceUuid)->firstOrFail();

         // Obter IDs dos artigos no escopo e verificar quais precisam de revisão
          $articleIdsToReview = collect();
          $articlesWithProgress = collect();
          $articleIdsInScope = $this->getArticlesInScopeForReview($phaseId, $allPhasesList); // Usa a lista de estrutura

          if ($articleIdsInScope->isNotEmpty()) {
               $articleIdsToReview = UserProgress::where('user_id', $userId)
                   ->whereIn('law_article_id', $articleIdsInScope)
                   ->where('percentage', '<', 100)
                   ->pluck('law_article_id');

              if ($articleIdsToReview->isNotEmpty()) {
                   $articlesWithProgress = $this->getArticlesWithProgress(
                       $userId,
                       LawArticle::with('options')
                           ->whereIn('id', $articleIdsToReview)
                           ->where('is_active', true)
                           ->orderBy('position', 'asc')
                           ->get()
                   );
               }
           }

         // Se não há artigos para revisar NESTE escopo
         if ($articlesWithProgress->isEmpty()) {
              Log::debug("[Review Route] Fase {$phaseId}: Não há artigos para revisar. Redirecionando para próxima fase.");
              $nextPhaseDetails = $this->findNextPhase($phaseId, $allPhasesList);
             if ($nextPhaseDetails) {
                 // Construir a rota correta para a próxima fase
                 $nextRoute = $nextPhaseDetails['is_review']
                     ? route('play.review', ['referenceUuid' => $nextPhaseDetails['reference_uuid'], 'phase' => $nextPhaseDetails['id']])
                     : route('play.phase', ['phaseId' => $nextPhaseDetails['id']]);

                  // Validação extra: a próxima fase calculada não deveria estar bloqueada
                  if (isset($nextPhaseDetails['is_blocked']) && $nextPhaseDetails['is_blocked']) {
                      Log::error("[Review Route] Erro de lógica: Próxima fase {$nextPhaseDetails['id']} após revisão {$phaseId} está bloqueada no findNextPhase.");
                      // Não redirecionar, talvez voltar ao mapa com erro
                      return redirect()->route('play.map')->with('message', 'Erro ao determinar a próxima fase.');
                  }

                  return redirect($nextRoute);
              } else {
                   // Log::debug("[Review Route] Fase {$phaseId}: Revisão completa e sem próxima fase. Voltando ao mapa.");
                   return redirect()->route('play.map')->with('message', 'Parabéns, você completou esta seção!');
              }
         }

         // Se há artigos para revisar, renderiza a página de revisão
        //  Log::debug("[Review Route] Fase {$phaseId}: Renderizando revisão com {$articlesWithProgress->count()} artigos.");
         $nextPhaseDetails = $this->findNextPhase($phaseId, $allPhasesList);

         return Inertia::render('Play/Phase', [ // Reutiliza o componente Phase
             'phase' => [
                 'title' => $phaseDetails['title'], // Título já formatado
                 'reference_name' => $reference->name,
                 'phase_number' => $phaseId, // ID Global
                 'is_review' => true,
                 'reference_uuid' => $referenceUuid,
                 'has_next_phase' => !!$nextPhaseDetails,
                 'next_phase_number' => $nextPhaseDetails ? $nextPhaseDetails['id'] : null,
                 'next_phase_is_review' => $nextPhaseDetails ? $nextPhaseDetails['is_review'] : false,
                 'progress' => $phaseDetails['progress'] // Passa o progresso da revisão
             ],
             'articles' => $articlesWithProgress
         ]);
     }

      // PlayController.php

    private function findPhaseDetailsById($userId, $targetPhaseId): array
    {
        //  Log::info("[findDetails - Start] Finding details for Phase ID: {$targetPhaseId}, User ID: {$userId}");
        $user = Auth::user();
        $hasPreferences = $user->legalReferences()->exists();

        // 1. Recalcular $legalReferences (Garantir que a query seja IDÊNTICA à do map())
        $legalReferencesQuery = LegalReference::with(['articles' => function($query) {
            $query->orderBy('position', 'asc')->where('is_active', true);
        }]);
        if ($hasPreferences) {
            $legalReferencesQuery->whereHas('users', function($query) use ($userId) {
                $query->where('users.id', $userId);
            });
        } else {
            if (!LegalReference::exists()) return [null, []];
            $legalReferencesQuery = LegalReference::with(['articles' => function($query) {
                $query->orderBy('position', 'asc')->where('is_active', true);
            }])->where('is_active', true);
        }
        $legalReferences = $legalReferencesQuery->orderBy('id', 'asc')->get();
        if ($legalReferences->isEmpty()) {
            // Log::warning("[findDetails] No legal references found based on user preferences/defaults.");
            return [null, []];
        }
        // Log::debug("[findDetails] Found " . $legalReferences->count() . " legal references.");


        // 2. Recalcular $phaseStructureList (IDÊNTICO ao Passo 1 do map())
        $phaseStructureList = [];
        $tempCounter = 0;
        foreach ($legalReferences as $reference) {
            $chunks = $reference->articles->chunk(self::ARTICLES_PER_PHASE);
            $regularPhaseCounter = 0;
            foreach ($chunks as $chunkIndex => $articleChunk) {
                $regularPhaseCounter++;
                $tempCounter++;
                $struct = [
                    'id' => $tempCounter, 'is_review' => false,
                    'reference_uuid' => $reference->uuid, 'chunk_index' => $chunkIndex,
                    'article_chunk' => $articleChunk, // Manter chunk para Pass 2
                    'reference_name_debug' => $reference->name // Add for logging
                ];
                if ($tempCounter == $targetPhaseId) {
                    // Log::debug("[findDetails - Struct Gen] Target ID {$targetPhaseId} Structure (Regular): " . json_encode([
                    //     'id' => $struct['id'], 'is_review' => $struct['is_review'],
                    //     'ref_uuid' => $struct['reference_uuid'], 'chunk' => $struct['chunk_index'],
                    //     'ref_name' => $struct['reference_name_debug']
                    // ]));
                }
                $phaseStructureList[] = $struct;

                if ($regularPhaseCounter % self::REVIEW_PHASE_INTERVAL === 0) {
                    $tempCounter++;
                    $struct = [
                        'id' => $tempCounter, 'is_review' => true,
                        'reference_uuid' => $reference->uuid,
                        'last_regular_counter' => $regularPhaseCounter,
                        'reference_name_debug' => $reference->name // Add for logging
                    ];
                    if ($tempCounter == $targetPhaseId) { 
                        // Log::debug("[findDetails - Struct Gen] Target ID {$targetPhaseId} Structure (Review): " . json_encode([
                        //     'id' => $struct['id'], 'is_review' => $struct['is_review'],
                        //     'ref_uuid' => $struct['reference_uuid'],
                        //     'ref_name' => $struct['reference_name_debug']
                        // ]));
                    }
                    $phaseStructureList[] = $struct;
                }
            }
        }
        // Log::debug("[findDetails] Recalculated structure list. Count: " . count($phaseStructureList));

        // 3. Iterar pela Estrutura para Calcular Dados (IDÊNTICO ao Passo 2 do map())
        $allPhasesListData = [];
        $phaseDetails = null;
        $currentPhaseId = null; // ID global da fase atual real
        $blockSubsequent = false;
        $previousPhaseIsComplete = true;
        $currentLawUuid_pass2 = null; // Usar variável diferente para evitar conflito
        $lawCompletionStatus_pass2 = []; // Usar variável diferente

        foreach ($phaseStructureList as $phaseIndex => $phaseStruct) {
            $currentPhaseGlobalId = $phaseStruct['id'];

            // Lógica de mudança de lei
            if ($phaseStruct['reference_uuid'] !== $currentLawUuid_pass2) {
                $previousLawUuid_pass2 = $currentLawUuid_pass2;
                $currentLawUuid_pass2 = $phaseStruct['reference_uuid'];
                // Bloqueio inter-lei
                if ($previousLawUuid_pass2 !== null && isset($lawCompletionStatus_pass2[$previousLawUuid_pass2]) && !$lawCompletionStatus_pass2[$previousLawUuid_pass2]) {
                    $blockSubsequent = true;
                }
                $lawCompletionStatus_pass2[$currentLawUuid_pass2] = true; // Assume completa
            }

            $isPhaseBlocked = $blockSubsequent || !$previousPhaseIsComplete;
            $isPhaseCurrent = false; // Reset for each phase
            $phaseProgress = null;
            $isPhaseComplete = false;
            $phaseBuiltData = null;

            // Obter o modelo da referência correspondente a ESTA fase da estrutura
            // Usar firstWhere no início da iteração para garantir a referência correta
            $currentReferenceModel = $legalReferences->firstWhere('uuid', $phaseStruct['reference_uuid']);
            if (!$currentReferenceModel) {
                // Log::error("[findDetails - Pass 2] Cannot find LegalReference model for UUID: {$phaseStruct['reference_uuid']} at Phase ID {$currentPhaseGlobalId}. Skipping.");
                continue; // Pula esta fase se a referência não for encontrada
            }

            if ($currentPhaseGlobalId == $targetPhaseId) {
                // Log::debug("[findDetails - Pass 2] Processing Target ID {$targetPhaseId}. Ref Name: {$currentReferenceModel->name}. Blocked: ".($isPhaseBlocked?'Y':'N').". PrevComplete: ".($previousPhaseIsComplete?'Y':'N'));
            }


            if ($phaseStruct['is_review']) {
                $articleIdsInScope = $this->getArticlesInScopeForReview($currentPhaseGlobalId, $phaseStructureList);
                $phaseProgress = $this->getReviewPhaseProgress($userId, $articleIdsInScope);
                $isPhaseComplete = $phaseProgress['is_complete'];
                if (!$isPhaseBlocked && !$isPhaseComplete && $currentPhaseId === null) { $isPhaseCurrent = true; $currentPhaseId = $currentPhaseGlobalId; }
                $phaseBuiltData = $this->buildReviewPhaseData(
                    $currentPhaseGlobalId, $currentReferenceModel, // Usa a ref correta
                    $phaseStruct['last_regular_counter'], $isPhaseBlocked, $isPhaseCurrent, $phaseProgress );
            } else {
                // Garante que 'article_chunk' existe na estrutura
                if (!isset($phaseStruct['article_chunk'])) {
                    Log::error("[findDetails - Pass 2] Missing 'article_chunk' for Regular Phase ID {$currentPhaseGlobalId}. Skipping.");
                    continue;
                }
                $phaseProgress = $this->getPhaseProgress($userId, $phaseStruct['article_chunk']);
                $isPhaseComplete = $phaseProgress['all_attempted'];
                if (!$isPhaseBlocked && !$isPhaseComplete && $currentPhaseId === null) { $isPhaseCurrent = true; $currentPhaseId = $currentPhaseGlobalId; }
                $phaseBuiltData = $this->buildPhaseData(
                    $currentPhaseGlobalId, $currentReferenceModel, // Usa a ref correta
                    $phaseStruct['article_chunk'], $phaseStruct['chunk_index'], $phaseProgress, $isPhaseBlocked, $isPhaseCurrent );
            }

            // Atualizar status da lei
            if (!$isPhaseComplete) {
                $lawCompletionStatus_pass2[$currentLawUuid_pass2] = false;
            }

            // Armazenar dados da fase alvo
            if ($currentPhaseGlobalId == $targetPhaseId) {
                Log::debug("[findDetails - Pass 2] Storing phaseDetails for Target ID {$targetPhaseId}. Data: " . json_encode($phaseBuiltData));
                $phaseDetails = $phaseBuiltData;
            }
            // Adicionar à lista completa de dados
            if ($phaseBuiltData) {
                $allPhasesListData[] = $phaseBuiltData;
            }

            $previousPhaseIsComplete = $isPhaseComplete;
            if ($isPhaseCurrent) { $blockSubsequent = true; }
        }

        // 4. Ajuste Final (IDÊNTICO ao Passo 3 do map())
        if ($currentPhaseId !== null) {
            // Corrigir is_current / is_blocked na lista completa
            foreach ($allPhasesListData as $index => $phase) {
                if ($phase['id'] === $currentPhaseId) {
                    $allPhasesListData[$index]['is_current'] = true;
                    $allPhasesListData[$index]['is_blocked'] = false;
                } elseif ($allPhasesListData[$index]['is_current']) {
                    $allPhasesListData[$index]['is_current'] = false;
                }
            }
            // Corrigir is_current / is_blocked nos detalhes da fase alvo, se ela for a atual
            if($phaseDetails && $phaseDetails['id'] === $currentPhaseId) {
                $phaseDetails['is_current'] = true;
                $phaseDetails['is_blocked'] = false;
            } elseif ($phaseDetails) {
                $phaseDetails['is_current'] = false; // Garante que não seja marcada como atual se não for
            }
        }

        if (!$phaseDetails) {
            Log::error("[findDetails - End] Target Phase ID {$targetPhaseId} was not found or built during Pass 2.");
        } else {
            Log::info("[findDetails - End] Returning details for Phase ID {$targetPhaseId}. Ref Name in details: " . $phaseDetails['reference_name']);
        }


        return [$phaseDetails, $allPhasesListData]; // Retorna detalhes da fase alvo e a lista completa de dados
    }

      private function findNextPhase($currentPhaseId, $allPhasesListData): ?array
      {
          $currentIndex = -1;
          foreach ($allPhasesListData as $index => $phase) {
              if ($phase['id'] == $currentPhaseId) {
                  $currentIndex = $index;
                  break;
              }
          }
          // Retorna o próximo item da lista de DADOS se existir
          if ($currentIndex !== -1 && isset($allPhasesListData[$currentIndex + 1])) {
              return $allPhasesListData[$currentIndex + 1];
          }
          return null;
      }

     private function getArticlesWithProgress($userId, Collection $articles): Collection
      {
            if ($articles->isEmpty()) return collect();
            $articleIds = $articles->pluck('id');
            $progressRecords = UserProgress::where('user_id', $userId)
                ->whereIn('law_article_id', $articleIds)->get()->keyBy('law_article_id');

            return $articles->map(function ($article) use ($progressRecords) {
                $progress = $progressRecords->get($article->id);
                return [ /* ... dados do artigo e progresso ... */
                     'uuid' => $article->uuid,
                     'article_reference' => $article->article_reference,
                     'original_content' => $article->original_content,
                     'practice_content' => $article->practice_content,
                     'options' => $article->options->map(fn($o)=>[
                          'id'=>$o->id, 'word'=>$o->word, 'is_correct'=>$o->is_correct,
                          'gap_order'=>$o->gap_order, 'position'=>$o->position
                     ])->sortBy('position')->values()->all(),
                     'progress' => $progress ? [
                         'percentage' => $progress->percentage,
                         'is_completed' => $progress->percentage >= 100,
                         'best_score' => $progress->best_score, 'attempts' => $progress->attempts,
                         'wrong_answers' => $progress->wrong_answers, 'revisions' => $progress->revisions,
                     ] : null,
                ];
            });
      }


    // Manter calculateAverageDifficulty, rewardLife, saveProgress
     private function calculateAverageDifficulty($articles)
    {
        if ($articles->isEmpty()) {
            return 1;
        }
        $totalDifficulty = $articles->sum('difficulty_level');
        return round($totalDifficulty / $articles->count());
    }
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
     public function saveProgress(Request $request)
    {
        $validated = $request->validate([
            'article_uuid' => 'required|string|exists:law_articles,uuid',
            'correct_answers' => 'required|integer|min:0',
            'total_answers' => 'required|integer|min:1',
        ]);

        $article = LawArticle::where('uuid', $validated['article_uuid'])->firstOrFail();
        $user = Auth::user();
        $percentage = ($validated['total_answers'] > 0)
                      ? (($validated['correct_answers'] / $validated['total_answers']) * 100)
                      : 0;

        // Garantir que correct_answers não seja maior que total_answers
        $correctAnswers = min((int)$validated['correct_answers'], (int)$validated['total_answers']);
        $totalAnswers = (int)$validated['total_answers'];


        // Perder vida apenas na *primeira vez* que falha (<70%) ou se já estava < 70% e continua < 70%?
        // Vamos implementar: perde vida se ficar < 70% nesta tentativa.
        $lostLife = false;
        if ($percentage < 70) {
             // Verifica se tem vidas para perder ou se tem vidas infinitas
             if ($user->lives > 0 && !$user->hasInfiniteLives()) {
                 $user->decrementLife();
                 $lostLife = true;
             }
        }


        $progress = UserProgress::updateProgress(
            $userId = $user->id, // Corrigido para pegar $user->id
            $article->id,
            $correctAnswers, // Usar valor corrigido
            $totalAnswers // Usar valor corrigido
        );

         // A função updateProgress deve retornar o objeto $progress atualizado
         // Vamos buscá-lo novamente para garantir os últimos dados, especialmente se updateProgress não retornar
         $updatedProgress = UserProgress::where('user_id', $user->id)->where('law_article_id', $article->id)->first();


        return response()->json([
            'success' => true,
            'progress' => $updatedProgress ? [ // Usar dados atualizados
                'percentage' => $updatedProgress->percentage,
                'is_completed' => $updatedProgress->is_completed,
                'best_score' => $updatedProgress->best_score,
                'attempts' => $updatedProgress->attempts,
                'wrong_answers' => $updatedProgress->wrong_answers,
                'revisions' => $updatedProgress->revisions,
            ] : null,
            'user' => [
                'lives' => $user->lives, // Vidas já decrementadas se necessário
                'has_infinite_lives' => $user->hasInfiniteLives()
            ],
             'lost_life' => $lostLife, // Informar se perdeu vida
            'should_redirect' => !$user->hasInfiniteLives() && $user->lives <= 0,
            'redirect_url' => !$user->hasInfiniteLives() && $user->lives <= 0 ? route('play.nolives') : null
        ]);
    }


} // Fim da classe