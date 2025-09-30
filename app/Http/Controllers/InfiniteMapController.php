<?php

namespace App\Http\Controllers;

use App\Models\LegalReference;
use App\Models\UserProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class InfiniteMapController extends Controller
{
    // === CONFIGURAÇÕES DE GERAÇÃO DE FASES (herdadas do PlayController) ===

    const ARTICLES_PER_PHASE = 6;

    const REVIEW_PHASE_INTERVAL = 3;

    const PHASES_PER_MODULE_PER_LAW = 6;

    /**
     * Renderiza o mapa com scroll infinito
     * Carrega inicialmente: fases completas + fase atual + algumas pendentes (~10-15 fases)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id;

        if (! $user->hasLives() && ! $user->hasActiveSubscription()) {
            return redirect()->route('play.nolives');
        }

        $hasPreferences = $user->legalReferences()->exists();

        // PASSO 1: Gerar estrutura completa de fases (mesma lógica do mapa convencional)
        $phaseStructureList = $this->buildCompletePhaseStructure($userId);

        if (empty($phaseStructureList)) {
            return redirect()->route('user.legal-references.index')
                ->with('message', $hasPreferences ? 'Nenhuma das suas leis selecionadas está disponível ou ativa.' : 'Selecione as leis que deseja estudar.');
        }

        // PASSO 2: Encontrar fase atual (CRÍTICO: apenas UMA fase pode ser atual)
        [$currentPhaseId, $currentPhaseIndex] = $this->findCurrentPhaseIndex($phaseStructureList, $userId);

        // PASSO 3: Calcular janela inicial de fases
        $initialWindow = $this->calculateInitialWindow($phaseStructureList, $currentPhaseIndex);

        // PASSO 4: Construir dados completos apenas da janela inicial
        // IMPORTANTE: Passar o currentPhaseId para garantir que apenas UMA fase seja marcada como atual
        $phasesData = $this->buildPhasesDataFromStructure($initialWindow, $userId, $currentPhaseId);

        // PASSO 5: Organizar em módulos (apenas fases da janela)
        $modulesData = $this->organizePhasesIntoModulesOptimized($phasesData);

        return Inertia::render('Play/InfiniteMap', [
            'phases' => $phasesData,
            'modules' => $modulesData,
            'meta' => [
                'current_phase_id' => $currentPhaseId,
                'current_phase_index' => $currentPhaseIndex,
                'total_phases' => count($phaseStructureList),
                'loaded_phases' => count($phasesData),
                'has_more' => count($initialWindow) < count($phaseStructureList),
                'next_offset' => count($initialWindow),
            ],
            'user' => [
                'lives' => $user->lives,
                'has_infinite_lives' => $user->hasInfiniteLives(),
            ],
        ]);
    }

    /**
     * Carrega mais fases para scroll infinito (retorna JSON)
     */
    public function loadMore(Request $request)
    {
        $validated = $request->validate([
            'offset' => 'required|integer|min:0',
            'limit' => 'integer|min:1|max:15',
        ]);

        $userId = Auth::user()->id;
        $offset = $validated['offset'];
        $limit = $validated['limit'] ?? 9; // Default: 9 fases (~1.5 módulos)

        // Regenerar estrutura completa
        $phaseStructureList = $this->buildCompletePhaseStructure($userId);

        if (empty($phaseStructureList) || $offset >= count($phaseStructureList)) {
            return response()->json([
                'phases' => [],
                'has_more' => false,
            ]);
        }

        // IMPORTANTE: Encontrar qual é a fase atual GLOBAL para garantir que não marquemos outras como atuais
        [$currentPhaseId, $currentPhaseIndex] = $this->findCurrentPhaseIndex($phaseStructureList, $userId);

        // Pegar próximo batch de fases
        $nextBatch = array_slice($phaseStructureList, $offset, $limit);

        if (empty($nextBatch)) {
            return response()->json([
                'phases' => [],
                'has_more' => false,
            ]);
        }

        // Construir dados completos do batch
        // CRÍTICO: Passar currentPhaseId para evitar marcar múltiplas fases como atuais
        $phasesData = $this->buildPhasesDataFromStructure($nextBatch, $userId, $currentPhaseId);

        return response()->json([
            'phases' => $phasesData,
            'has_more' => $offset + $limit < count($phaseStructureList),
            'next_offset' => $offset + $limit,
        ]);
    }

    /**
     * Constrói a estrutura completa de fases (array com metadata, sem dados pesados)
     * Retorna array de estruturas: ['id', 'is_review', 'reference_uuid', 'chunk_index', 'article_chunk']
     */
    private function buildCompletePhaseStructure(int $userId): array
    {
        $user = Auth::user();
        $hasPreferences = $user->legalReferences()->exists();

        // Buscar referências legais (mesma query do map())
        $legalReferencesQuery = LegalReference::with(['articles' => function ($query) {
            $query->orderByRaw('CAST(article_reference AS UNSIGNED) ASC')->where('is_active', true);
        }]);

        if ($hasPreferences) {
            $legalReferencesQuery->whereHas('users', function ($query) use ($userId) {
                $query->where('users.id', $userId);
            });
        } else {
            if (! LegalReference::exists()) {
                return [];
            }
            $legalReferencesQuery->where('is_active', true);
        }

        $legalReferences = $legalReferencesQuery->orderBy('id', 'asc')->get();

        if ($legalReferences->isEmpty()) {
            return [];
        }

        // Preparar chunks de todas as leis
        $lawChunksData = [];
        $maxChunks = 0;

        foreach ($legalReferences as $reference) {
            $chunks = $reference->articles->chunk(self::ARTICLES_PER_PHASE);
            $lawChunksData[$reference->uuid] = [
                'reference' => $reference,
                'chunks' => $chunks,
                'total_chunks' => $chunks->count(),
            ];
            $maxChunks = max($maxChunks, $chunks->count());
        }

        // Intercalar fases de forma rigorosa (mesma lógica do map())
        $phaseStructureList = [];
        $tempCounter = 0;
        $totalModules = ceil($maxChunks / self::PHASES_PER_MODULE_PER_LAW);

        for ($moduleIndex = 0; $moduleIndex < $totalModules; $moduleIndex++) {
            $startChunkIndex = $moduleIndex * self::PHASES_PER_MODULE_PER_LAW;
            $endChunkIndex = $startChunkIndex + self::PHASES_PER_MODULE_PER_LAW;

            foreach ($lawChunksData as $lawUuid => $lawData) {
                $phasesAddedForThisLaw = 0;

                // Adicionar fases regulares
                for ($chunkIndex = $startChunkIndex; $chunkIndex < $endChunkIndex && $phasesAddedForThisLaw < self::PHASES_PER_MODULE_PER_LAW; $chunkIndex++) {
                    if ($chunkIndex < $lawData['total_chunks']) {
                        $tempCounter++;

                        $phaseStructureList[] = [
                            'id' => $tempCounter,
                            'is_review' => false,
                            'reference_uuid' => $lawUuid,
                            'chunk_index' => $chunkIndex,
                            'article_chunk' => $lawData['chunks'][$chunkIndex],
                            'reference' => $lawData['reference'], // Incluir referência para facilitar
                        ];

                        $phasesAddedForThisLaw++;
                    }
                }

                // Adicionar revisão se necessário
                if ($phasesAddedForThisLaw > 0) {
                    $totalRegularPhasesOfThisLaw = min($endChunkIndex, $lawData['total_chunks']);

                    if ($totalRegularPhasesOfThisLaw % self::REVIEW_PHASE_INTERVAL === 0) {
                        $tempCounter++;
                        $phaseStructureList[] = [
                            'id' => $tempCounter,
                            'is_review' => true,
                            'reference_uuid' => $lawUuid,
                            'last_regular_counter' => $totalRegularPhasesOfThisLaw,
                            'reference' => $lawData['reference'],
                        ];
                    }
                }
            }
        }

        return $phaseStructureList;
    }

    /**
     * Encontra o índice da fase atual na estrutura
     * CRÍTICO: Retorna apenas UMA fase atual - a primeira incompleta e não bloqueada
     * Retorna: [currentPhaseId, currentPhaseIndex]
     */
    private function findCurrentPhaseIndex(array $phaseStructureList, int $userId): array
    {
        $currentPhaseId = null;
        $currentPhaseIndex = 0;
        $blockSubsequent = false;
        $previousPhaseIsComplete = true;
        $currentLawUuid = null;
        $lawCompletionStatus = [];

        foreach ($phaseStructureList as $index => $phaseStruct) {
            $currentPhaseGlobalId = $phaseStruct['id'];

            // Verificar mudança de lei
            if ($phaseStruct['reference_uuid'] !== $currentLawUuid) {
                $previousLawUuid = $currentLawUuid;
                $currentLawUuid = $phaseStruct['reference_uuid'];

                if ($previousLawUuid !== null && isset($lawCompletionStatus[$previousLawUuid]) && ! $lawCompletionStatus[$previousLawUuid]) {
                    $blockSubsequent = true;
                }
                $lawCompletionStatus[$currentLawUuid] = true;
            }

            $isPhaseBlocked = $blockSubsequent || ! $previousPhaseIsComplete;
            $isPhaseComplete = false;

            // Calcular conclusão da fase
            if ($phaseStruct['is_review']) {
                $articleIdsInScope = $this->getArticlesInScopeForReview($currentPhaseGlobalId, $phaseStructureList);
                $phaseProgress = $this->getReviewPhaseProgress($userId, $articleIdsInScope);
                $isPhaseComplete = $phaseProgress['is_complete'];
            } else {
                if (! isset($phaseStruct['article_chunk'])) {
                    continue;
                }
                $phaseProgress = $this->getPhaseProgress($userId, $phaseStruct['article_chunk']);
                $isPhaseComplete = $phaseProgress['all_attempted'];
            }

            // CRÍTICO: Esta é a fase atual? (apenas a PRIMEIRA incompleta e não bloqueada)
            if (! $isPhaseBlocked && ! $isPhaseComplete && $currentPhaseId === null) {
                $currentPhaseId = $currentPhaseGlobalId;
                $currentPhaseIndex = $index;
                $blockSubsequent = true; // Bloquear todas as subsequentes
                // Parar aqui - não precisamos continuar iterando para encontrar fase atual
                // MAS precisamos continuar para calcular bloqueios corretamente
            }

            // Atualizar estado da lei
            if (! $isPhaseComplete) {
                $lawCompletionStatus[$currentLawUuid] = false;
            }

            // Atualizar para próxima iteração
            $previousPhaseIsComplete = $isPhaseComplete;
        }

        return [$currentPhaseId, $currentPhaseIndex];
    }

    /**
     * Calcula a janela inicial de fases a serem carregadas
     * Inclui: todas as fases completas + fase atual + 5-8 fases à frente
     */
    private function calculateInitialWindow(array $phaseStructureList, int $currentPhaseIndex): array
    {
        $totalPhases = count($phaseStructureList);

        // Carregar desde o início até 8 fases à frente da atual
        $startIndex = 0;
        $endIndex = min($currentPhaseIndex + 8, $totalPhases);

        return array_slice($phaseStructureList, $startIndex, $endIndex - $startIndex);
    }

    /**
     * Constrói os dados completos das fases a partir da estrutura
     * CRÍTICO: Recebe $globalCurrentPhaseId para garantir que apenas UMA fase seja marcada como atual
     */
    private function buildPhasesDataFromStructure(array $phaseStructures, int $userId, ?int $globalCurrentPhaseId): array
    {
        $phasesData = [];
        $blockSubsequent = false;
        $previousPhaseIsComplete = true;
        $currentLawUuid = null;
        $lawCompletionStatus = [];
        $foundCurrentPhase = false;

        // CRÍTICO: Verificar se a fase atual global está ANTES deste batch
        // Se estiver, significa que todas as fases deste batch devem ser bloqueadas
        $currentPhaseIsBeforeThisBatch = false;
        if ($globalCurrentPhaseId !== null && ! empty($phaseStructures)) {
            $firstPhaseIdInBatch = $phaseStructures[0]['id'];
            if ($globalCurrentPhaseId < $firstPhaseIdInBatch) {
                $currentPhaseIsBeforeThisBatch = true;
                $blockSubsequent = true; // Bloquear tudo desde o início
            }
        }

        foreach ($phaseStructures as $phaseStruct) {
            $currentPhaseGlobalId = $phaseStruct['id'];

            // Lógica de mudança de lei
            if ($phaseStruct['reference_uuid'] !== $currentLawUuid) {
                $previousLawUuid = $currentLawUuid;
                $currentLawUuid = $phaseStruct['reference_uuid'];

                if ($previousLawUuid !== null && isset($lawCompletionStatus[$previousLawUuid]) && ! $lawCompletionStatus[$previousLawUuid]) {
                    $blockSubsequent = true;
                }
                $lawCompletionStatus[$currentLawUuid] = true;
            }

            // CRÍTICO: Se já encontramos a fase atual OU se a fase atual está antes deste batch
            if ($foundCurrentPhase || $currentPhaseIsBeforeThisBatch) {
                $isPhaseBlocked = true;
            } else {
                $isPhaseBlocked = $blockSubsequent || ! $previousPhaseIsComplete;
            }

            // CRÍTICO: Apenas marcar como atual se for a fase atual GLOBAL
            $isPhaseCurrent = ($currentPhaseGlobalId === $globalCurrentPhaseId);
            $isPhaseComplete = false;
            $phaseBuiltData = null;

            if ($phaseStruct['is_review']) {
                // Precisamos da estrutura completa para calcular escopo da revisão
                $fullStructure = $this->buildCompletePhaseStructure($userId);
                $articleIdsInScope = $this->getArticlesInScopeForReview($currentPhaseGlobalId, $fullStructure);
                $phaseProgress = $this->getReviewPhaseProgress($userId, $articleIdsInScope);
                $isPhaseComplete = $phaseProgress['is_complete'];

                $phaseBuiltData = $this->buildReviewPhaseData(
                    $currentPhaseGlobalId,
                    $phaseStruct['reference'],
                    $phaseStruct['last_regular_counter'],
                    $isPhaseBlocked,
                    $isPhaseCurrent,
                    $phaseProgress
                );
            } else {
                if (! isset($phaseStruct['article_chunk'])) {
                    continue;
                }

                $phaseProgress = $this->getPhaseProgress($userId, $phaseStruct['article_chunk']);
                $isPhaseComplete = $phaseProgress['all_attempted'];

                $phaseBuiltData = $this->buildPhaseData(
                    $currentPhaseGlobalId,
                    $phaseStruct['reference'],
                    $phaseStruct['article_chunk'],
                    $phaseStruct['chunk_index'],
                    $phaseProgress,
                    $isPhaseBlocked,
                    $isPhaseCurrent
                );
            }

            if (! $isPhaseComplete) {
                $lawCompletionStatus[$currentLawUuid] = false;
            }

            if ($phaseBuiltData) {
                $phasesData[] = $phaseBuiltData;
            }

            $previousPhaseIsComplete = $isPhaseComplete;

            // CRÍTICO: Quando encontramos a fase atual, marcar flag e bloquear todas as subsequentes
            if ($isPhaseCurrent) {
                $foundCurrentPhase = true;
                $blockSubsequent = true;
            }
        }

        return $phasesData;
    }

    // ========== MÉTODOS AUXILIARES (copiados do PlayController) ==========

    private function buildPhaseData(int $phaseId, LegalReference $reference, Collection $articleChunk, int $chunkIndex, array $progress, bool $isBlocked, bool $isCurrent): array
    {
        return [
            'id' => $phaseId,
            'title' => 'Fase '.$phaseId,
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
                'article_status' => $progress['article_status'] ?? [],
            ],
            'is_blocked' => $isBlocked,
            'is_current' => $isCurrent,
            'is_review' => false,
        ];
    }

    private function buildReviewPhaseData(int $phaseId, LegalReference $reference, int $lastRegularPhaseCounter, bool $isBlocked, bool $isCurrent, ?array $progress = null): array
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
            'title' => 'Revisão '.ceil($lastRegularPhaseCounter / self::REVIEW_PHASE_INTERVAL),
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

    private function getPhaseProgress($userId, Collection $articles)
    {
        if (! $userId || $articles->isEmpty()) {
            return [
                'completed' => 0, 'total' => $articles->count(), 'percentage' => 0,
                'article_status' => array_fill(0, $articles->count(), 'pending'),
                'is_fully_complete' => false, 'all_attempted' => false,
            ];
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
            'all_attempted' => $allAttempted,
        ];
    }

    private function getReviewPhaseProgress($userId, Collection $articleIdsInScope)
    {
        if (! $userId || $articleIdsInScope->isEmpty()) {
            return [
                'is_complete' => true, 'needs_review' => false, 'articles_to_review_count' => 0,
                'completed' => 0, 'total' => 0, 'percentage' => 0, 'article_status' => [],
                'is_fully_complete' => true, 'all_attempted' => true,
            ];
        }

        $incompleteArticlesCount = UserProgress::where('user_id', $userId)
            ->whereIn('law_article_id', $articleIdsInScope)
            ->where('percentage', '<', 100)
            ->count();

        $needsReview = $incompleteArticlesCount > 0;

        return [
            'is_complete' => ! $needsReview, 'needs_review' => $needsReview,
            'articles_to_review_count' => $incompleteArticlesCount,
            'completed' => 0, 'total' => $incompleteArticlesCount, 'percentage' => 0, 'article_status' => [],
            'is_fully_complete' => ! $needsReview, 'all_attempted' => ! $needsReview,
        ];
    }

    private function getArticlesInScopeForReview(int $reviewPhaseId, array $fullPhaseStructureList): Collection
    {
        $articlesInScope = collect();
        $referenceUuid = null;
        $startIndex = -1;
        $endIndex = -1;
        $currentReviewPhaseIndex = -1;

        // 1. Find target review phase
        foreach ($fullPhaseStructureList as $index => $phaseStruct) {
            if ($phaseStruct['id'] === $reviewPhaseId && $phaseStruct['is_review']) {
                $referenceUuid = $phaseStruct['reference_uuid'];
                $currentReviewPhaseIndex = $index;
                break;
            }
        }
        if ($currentReviewPhaseIndex === -1 || $referenceUuid === null) {
            return collect();
        }

        // 2. Find start index
        $startIndex = 0;
        $foundBoundary = false;
        for ($i = $currentReviewPhaseIndex - 1; $i >= 0; $i--) {
            $phase = $fullPhaseStructureList[$i];
            if ($phase['reference_uuid'] !== $referenceUuid) {
                $startIndex = $i + 1;
                $foundBoundary = true;
                break;
            }
            if ($phase['is_review']) {
                $startIndex = $i + 1;
                $foundBoundary = true;
                break;
            }
        }
        if (! $foundBoundary) {
            foreach ($fullPhaseStructureList as $idx => $p) {
                if ($p['reference_uuid'] === $referenceUuid) {
                    $startIndex = $idx;
                    break;
                }
            }
        }

        // 3. End index
        $endIndex = $currentReviewPhaseIndex - 1;

        // 4. Collect article IDs
        $articleIds = collect();
        if ($startIndex <= $endIndex) {
            static $referenceArticleCache = []; // Static cache within the request
            if (! isset($referenceArticleCache[$referenceUuid])) {
                $refModel = LegalReference::with(['articles' => fn ($q) => $q->orderByRaw('CAST(article_reference AS UNSIGNED) ASC')])->where('uuid', $referenceUuid)->first();
                $referenceArticleCache[$referenceUuid] = $refModel ? $refModel->articles : collect();
            }
            $allArticlesOfRef = $referenceArticleCache[$referenceUuid];

            if ($allArticlesOfRef->isEmpty()) {
                Log::warning("[ScopeReview {$reviewPhaseId}] No articles found for ref {$referenceUuid}.");

                return collect();
            }

            $chunks = $allArticlesOfRef->chunk(self::ARTICLES_PER_PHASE);

            for ($i = $startIndex; $i <= $endIndex; $i++) {
                if (! isset($fullPhaseStructureList[$i])) {
                    continue;
                }
                $phase = $fullPhaseStructureList[$i];
                if (! $phase['is_review'] && isset($phase['chunk_index'])) {
                    $chunkIndex = $phase['chunk_index'];
                    if (isset($chunks[$chunkIndex])) {
                        $chunkArticleIds = $chunks[$chunkIndex]->pluck('id');
                        $articleIds = $articleIds->merge($chunkArticleIds);
                    }
                }
            }
        }

        $uniqueArticleIds = $articleIds->unique();

        // 5. Return collection of IDs
        return $uniqueArticleIds;
    }

    private function calculateAverageDifficulty($articles)
    {
        if ($articles->isEmpty()) {
            return 1;
        }
        $totalDifficulty = $articles->sum('difficulty_level');

        return round($totalDifficulty / $articles->count());
    }

    private function organizePhasesIntoModulesOptimized(array $phasesData): array
    {
        if (empty($phasesData)) {
            return [];
        }

        // Se há apenas uma lei, não mostrar módulos
        $uniqueRefs = array_unique(array_column($phasesData, 'reference_uuid'));
        if (count($uniqueRefs) <= 1) {
            return [];
        }

        // Aproximação: ~16 fases por módulo (2 leis × 8 fases)
        $APPROX_PHASES_PER_MODULE = 16;

        $modules = [];
        $currentModuleId = 1;

        for ($i = 0; $i < count($phasesData); $i += $APPROX_PHASES_PER_MODULE) {
            $modulePhases = array_slice($phasesData, $i, $APPROX_PHASES_PER_MODULE);

            // Agrupar por referência dentro do módulo
            $refGroups = [];

            foreach ($modulePhases as $phase) {
                $uuid = $phase['reference_uuid'];
                if (! isset($refGroups[$uuid])) {
                    $refGroups[$uuid] = [
                        'reference_name' => $phase['reference_name'],
                        'reference_uuid' => $uuid,
                        'phase_ids' => [],
                    ];
                }
                $refGroups[$uuid]['phase_ids'][] = $phase['id'];
            }

            $modules[] = [
                'id' => $currentModuleId++,
                'title' => 'Módulo '.($currentModuleId - 1),
                'references' => array_values($refGroups),
            ];
        }

        return $modules;
    }
}
