<?php

namespace App\Http\Controllers;

use App\Models\LegalReference;
use App\Models\UserProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LearnController extends Controller
{
    // Usar as mesmas constantes do PlayController para consistência
    const ARTICLES_PER_PHASE = 6;

    const REVIEW_PHASE_INTERVAL = 3;

    const PHASES_PER_MODULE_PER_LAW = 6;

    const PHASES_PER_JOURNEY = 24;

    public function map(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id;

        if (! $user->hasLives() && ! $user->hasActiveSubscription()) {
            return redirect()->route('play.nolives');
        }

        $hasPreferences = $user->legalReferences()->exists();

        // Obter jornada atual (padrão: detectar automaticamente)
        $requestedJourney = $request->get('jornada');
        $currentJourney = 1;

        $legalReferencesQuery = LegalReference::with(['articles' => function ($query) {
            $query->orderByRaw('CAST(article_reference AS UNSIGNED) ASC')->where('is_active', true);
        }]);

        if ($hasPreferences) {
            $legalReferencesQuery->whereHas('users', function ($query) use ($userId) {
                $query->where('users.id', $userId);
            });
        } else {
            if (! LegalReference::exists()) {
                return redirect()->route('learn.map')->with('message', 'Nenhuma lei disponível no momento.');
            }
            $legalReferencesQuery = LegalReference::with(['articles' => function ($query) {
                $query->orderByRaw('CAST(article_reference AS UNSIGNED) ASC')->where('is_active', true);
            }])->where('is_active', true);
        }

        $legalReferences = $legalReferencesQuery->orderBy('id', 'asc')->get();

        if ($legalReferences->isEmpty()) {
            return redirect()->route('user.legal-references.index')
                ->with('message', $hasPreferences ? 'Nenhuma das suas leis selecionadas está disponível ou ativa.' : 'Selecione as leis que deseja estudar.');
        }

        // --- PASSO 1: Construir estrutura de fases intercaladas (idêntico ao PlayController) ---
        $phaseStructureList = [];
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

        $tempCounter = 0;
        $totalModules = ceil($maxChunks / self::PHASES_PER_MODULE_PER_LAW);

        for ($moduleIndex = 0; $moduleIndex < $totalModules; $moduleIndex++) {
            $startChunkIndex = $moduleIndex * self::PHASES_PER_MODULE_PER_LAW;
            $endChunkIndex = $startChunkIndex + self::PHASES_PER_MODULE_PER_LAW;

            foreach ($lawChunksData as $lawUuid => $lawData) {
                $phasesAddedForThisLaw = 0;

                for ($chunkIndex = $startChunkIndex; $chunkIndex < $endChunkIndex && $phasesAddedForThisLaw < self::PHASES_PER_MODULE_PER_LAW; $chunkIndex++) {
                    if ($chunkIndex < $lawData['total_chunks']) {
                        $tempCounter++;

                        $phaseStructureList[] = [
                            'id' => $tempCounter,
                            'is_review' => false,
                            'reference_uuid' => $lawUuid,
                            'chunk_index' => $chunkIndex,
                            'article_chunk' => $lawData['chunks'][$chunkIndex],
                        ];

                        $phasesAddedForThisLaw++;
                    }
                }

                if ($phasesAddedForThisLaw > 0) {
                    $totalRegularPhasesOfThisLaw = min($endChunkIndex, $lawData['total_chunks']);

                    if ($totalRegularPhasesOfThisLaw % self::REVIEW_PHASE_INTERVAL === 0) {
                        $tempCounter++;
                        $phaseStructureList[] = [
                            'id' => $tempCounter,
                            'is_review' => true,
                            'reference_uuid' => $lawUuid,
                            'last_regular_counter' => $totalRegularPhasesOfThisLaw,
                        ];
                    }
                }
            }
        }

        // --- PASSO 2: Iterar pela estrutura para calcular estados otimizados ---
        $phasesData = [];
        $currentPhaseId = null;
        $blockSubsequent = false;
        $previousPhaseIsComplete = true;
        $currentLawUuid = null;
        $lawCompletionStatus = [];

        foreach ($phaseStructureList as $phaseIndex => $phaseStruct) {
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
            $isPhaseCurrent = false;
            $isPhaseComplete = false;
            $phaseBuiltData = null;

            if ($phaseStruct['is_review']) {
                // Fase de revisão
                $articleIdsInScope = $this->getArticlesInScopeForReview($currentPhaseGlobalId, $phaseStructureList);
                $phaseProgress = $this->getReviewPhaseProgress($userId, $articleIdsInScope);
                $isPhaseComplete = $phaseProgress['is_complete'];

                if (! $isPhaseBlocked && ! $isPhaseComplete && $currentPhaseId === null) {
                    $isPhaseCurrent = true;
                    $currentPhaseId = $currentPhaseGlobalId;
                }

                $phaseBuiltData = $this->buildOptimizedReviewPhaseData(
                    $currentPhaseGlobalId,
                    $phaseStruct['reference_uuid'],
                    $isPhaseBlocked,
                    $isPhaseCurrent,
                    $isPhaseComplete,
                    $phaseProgress
                );
            } else {
                // Fase regular
                $phaseProgress = $this->getPhaseProgress($userId, $phaseStruct['article_chunk']);
                $isPhaseComplete = $phaseProgress['all_attempted'];

                if (! $isPhaseBlocked && ! $isPhaseComplete && $currentPhaseId === null) {
                    $isPhaseCurrent = true;
                    $currentPhaseId = $currentPhaseGlobalId;
                }

                $phaseBuiltData = $this->buildOptimizedPhaseData(
                    $currentPhaseGlobalId,
                    $phaseStruct['reference_uuid'],
                    $isPhaseBlocked,
                    $isPhaseCurrent,
                    $isPhaseComplete
                );
            }

            if (! $isPhaseComplete) {
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

        // --- AUTO-DETECÇÃO DA JORNADA ATUAL ---
        if ($requestedJourney === null && $currentPhaseId !== null) {
            $totalPhases = count($phasesData);
            $currentPhaseIndex = -1;

            foreach ($phasesData as $index => $phase) {
                if ($phase['id'] === $currentPhaseId) {
                    $currentPhaseIndex = $index;
                    break;
                }
            }

            if ($currentPhaseIndex !== -1) {
                $detectedJourney = floor($currentPhaseIndex / self::PHASES_PER_JOURNEY) + 1;
                $currentJourney = $detectedJourney;
            }
        } elseif ($requestedJourney !== null) {
            $currentJourney = max(1, (int) $requestedJourney);
        }

        // --- PASSO 3: Ajuste final - garantir que fase atual não está bloqueada ---
        if ($currentPhaseId !== null) {
            foreach ($phasesData as $index => $phase) {
                if ($phase['id'] === $currentPhaseId) {
                    $phasesData[$index]['is_current'] = true;
                    $phasesData[$index]['is_blocked'] = false;
                } elseif ($phase['is_current']) {
                    $phasesData[$index]['is_current'] = false;
                }
            }
        }

        // --- Sistema de jornadas ---
        $totalPhases = count($phasesData);
        $totalJourneys = ceil($totalPhases / self::PHASES_PER_JOURNEY);

        if ($currentJourney > $totalJourneys) {
            $currentJourney = $totalJourneys;
        }

        $startIndex = ($currentJourney - 1) * self::PHASES_PER_JOURNEY;
        $endIndex = min($startIndex + self::PHASES_PER_JOURNEY, $totalPhases);
        $journeyPhases = array_slice($phasesData, $startIndex, $endIndex - $startIndex);

        // Organizar em módulos otimizados
        $modulesData = $this->organizePhasesIntoModulesOptimized($journeyPhases, $legalReferences);

        $journeyInfo = [
            'current' => $currentJourney,
            'total' => $totalJourneys,
            'has_previous' => $currentJourney > 1,
            'has_next' => $currentJourney < $totalJourneys,
            'phases_in_journey' => count($journeyPhases),
            'total_phases' => $totalPhases,
            'journey_title' => $totalJourneys > 1 ? "Jornada {$currentJourney} de {$totalJourneys}" : null,
            'current_phase_id' => $currentPhaseId,
            'was_auto_detected' => $requestedJourney === null,
        ];

        return Inertia::render('Learn/Map', [
            'phases' => $journeyPhases,
            'modules' => $modulesData,
            'journey' => $journeyInfo,
            'user' => [
                'lives' => $user->lives,
                'has_infinite_lives' => $user->hasInfiniteLives(),
            ],
        ]);
    }

    // ===== MÉTODOS OTIMIZADOS =====

    /**
     * Constrói dados OTIMIZADOS de fase regular - payload mínimo
     */
    private function buildOptimizedPhaseData(
        int $phaseId,
        string $referenceUuid,
        bool $isBlocked,
        bool $isCurrent,
        bool $isComplete
    ): array {
        // Retornar apenas dados essenciais
        // A fase atual será acessível via route('play.phase', phaseId)
        // que é o ID numérico da fase, não UUID
        return [
            'id' => $phaseId,
            'reference_uuid' => $referenceUuid,
            'is_blocked' => $isBlocked,
            'is_current' => $isCurrent,
            'is_complete' => $isComplete,
            'is_review' => false,
        ];
    }

    /**
     * Constrói dados OTIMIZADOS de fase de revisão - payload mínimo
     */
    private function buildOptimizedReviewPhaseData(
        int $phaseId,
        string $referenceUuid,
        bool $isBlocked,
        bool $isCurrent,
        bool $isComplete,
        array $phaseProgress
    ): array {
        return [
            'id' => $phaseId,
            'reference_uuid' => $referenceUuid,
            'is_blocked' => $isBlocked,
            'is_current' => $isCurrent,
            'is_complete' => $isComplete,
            'is_review' => true,
            // Incluir apenas dados essenciais de progresso para revisões
            'progress' => [
                'needs_review' => $phaseProgress['needs_review'] ?? false,
                'articles_to_review_count' => $phaseProgress['articles_to_review_count'] ?? 0,
            ],
        ];
    }

    /**
     * Organiza fases em módulos - versão otimizada com apenas IDs
     */
    private function organizePhasesIntoModulesOptimized(array $phasesData, Collection $legalReferences): array
    {
        if (empty($phasesData)) {
            return [];
        }

        // Obter referências únicas
        $uniqueRefs = [];
        foreach ($phasesData as $phase) {
            $uniqueRefs[$phase['reference_uuid']] = true;
        }

        // Se há apenas uma lei, não mostrar módulos
        if (count($uniqueRefs) <= 1) {
            return [];
        }

        $user = Auth::user();
        $hasPreferences = $user->legalReferences()->exists();

        $totalLawsQuery = LegalReference::query();
        if ($hasPreferences) {
            $totalLawsQuery->whereHas('users', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            });
        } else {
            $totalLawsQuery->where('is_active', true);
        }
        $totalSelectedLaws = $totalLawsQuery->count();

        $approximatePhasesPerModule = self::PHASES_PER_MODULE_PER_LAW * $totalSelectedLaws;
        $approximateReviewsPerModule = $totalSelectedLaws * ceil(self::PHASES_PER_MODULE_PER_LAW / self::REVIEW_PHASE_INTERVAL);
        $approximatePhasesPerModule += $approximateReviewsPerModule;

        $firstPhaseId = $phasesData[0]['id'];
        $startingModuleNumber = max(1, ceil($firstPhaseId / $approximatePhasesPerModule));

        $modules = [];
        $moduleNumber = $startingModuleNumber;
        $totalPhases = count($phasesData);

        for ($startIndex = 0; $startIndex < $totalPhases; $startIndex += $approximatePhasesPerModule) {
            $endIndex = min($startIndex + $approximatePhasesPerModule, $totalPhases);
            $modulePhasesSlice = array_slice($phasesData, $startIndex, $endIndex - $startIndex);

            if (empty($modulePhasesSlice)) {
                continue;
            }

            // Agrupar por referência - APENAS IDs
            $referenceGroups = [];
            foreach ($modulePhasesSlice as $phase) {
                $uuid = $phase['reference_uuid'];
                if (! isset($referenceGroups[$uuid])) {
                    // Buscar nome da referência
                    $refModel = $legalReferences->firstWhere('uuid', $uuid);
                    $referenceGroups[$uuid] = [
                        'reference_name' => $refModel ? $refModel->name : 'Lei',
                        'reference_uuid' => $uuid,
                        'phase_ids' => [],
                    ];
                }
                $referenceGroups[$uuid]['phase_ids'][] = $phase['id'];
            }

            if (! empty($referenceGroups)) {
                $modules[] = [
                    'id' => $moduleNumber,
                    'title' => "Módulo {$moduleNumber}",
                    'references' => array_values($referenceGroups),
                ];
                $moduleNumber++;
            }
        }

        return $modules;
    }

    // ===== MÉTODOS AUXILIARES (reutilizados do PlayController) =====

    private function getPhaseProgress($userId, Collection $articles)
    {
        if (! $userId || $articles->isEmpty()) {
            return [
                'completed' => 0,
                'total' => $articles->count(),
                'percentage' => 0,
                'is_fully_complete' => false,
                'all_attempted' => false,
            ];
        }

        $articleIds = $articles->pluck('id');
        $progressRecords = UserProgress::where('user_id', $userId)
            ->whereIn('law_article_id', $articleIds)
            ->get()
            ->keyBy('law_article_id');

        $totalArticles = $articleIds->count();
        $correctCount = 0;
        $attemptedCount = 0;

        foreach ($articles as $article) {
            $progress = $progressRecords->get($article->id);
            if ($progress) {
                $attemptedCount++;
                if ($progress->percentage >= 100) {
                    $correctCount++;
                }
            }
        }

        $isFullyComplete = ($correctCount === $totalArticles);
        $allAttempted = ($attemptedCount === $totalArticles);

        return [
            'completed' => $attemptedCount,
            'total' => $totalArticles,
            'percentage' => $totalArticles > 0 ? round(($attemptedCount / $totalArticles) * 100, 2) : 0,
            'is_fully_complete' => $isFullyComplete,
            'all_attempted' => $allAttempted,
        ];
    }

    private function getReviewPhaseProgress($userId, Collection $articleIdsInScope)
    {
        if (! $userId || $articleIdsInScope->isEmpty()) {
            return [
                'is_complete' => true,
                'needs_review' => false,
                'articles_to_review_count' => 0,
            ];
        }

        $incompleteArticlesCount = UserProgress::where('user_id', $userId)
            ->whereIn('law_article_id', $articleIdsInScope)
            ->where('percentage', '<', 100)
            ->count();

        $needsReview = $incompleteArticlesCount > 0;

        return [
            'is_complete' => ! $needsReview,
            'needs_review' => $needsReview,
            'articles_to_review_count' => $incompleteArticlesCount,
        ];
    }

    private function getArticlesInScopeForReview(int $reviewPhaseId, array $fullPhaseStructureList): Collection
    {
        $referenceUuid = null;
        $currentReviewPhaseIndex = -1;

        // Encontrar fase de revisão
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

        // Encontrar índice inicial
        $startIndex = 0;
        $foundBoundary = false;
        for ($i = $currentReviewPhaseIndex - 1; $i >= 0; $i--) {
            $phase = $fullPhaseStructureList[$i];
            if ($phase['reference_uuid'] !== $referenceUuid || $phase['is_review']) {
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

        $endIndex = $currentReviewPhaseIndex - 1;

        // Coletar IDs de artigos
        $articleIds = collect();
        if ($startIndex <= $endIndex) {
            static $referenceArticleCache = [];
            if (! isset($referenceArticleCache[$referenceUuid])) {
                $refModel = LegalReference::with(['articles' => fn ($q) => $q->orderByRaw('CAST(article_reference AS UNSIGNED) ASC')->where('is_active', true)])
                    ->where('uuid', $referenceUuid)
                    ->first();
                $referenceArticleCache[$referenceUuid] = $refModel ? $refModel->articles : collect();
            }
            $allArticlesOfRef = $referenceArticleCache[$referenceUuid];

            if ($allArticlesOfRef->isEmpty()) {
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

        return $articleIds->unique();
    }
}
