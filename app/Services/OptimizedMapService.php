<?php

namespace App\Services;

use App\Models\LawArticle;
use App\Models\LegalReference;
use App\Models\UserProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class OptimizedMapService
{
    // === CONFIGURAÇÕES ===
    const ARTICLES_PER_PHASE = 6;
    const REVIEW_PHASE_INTERVAL = 3;
    const PHASES_PER_MODULE_PER_LAW = 6;
    const PHASES_PER_JOURNEY = 24;

    // Cache keys
    const CACHE_STRUCTURE_KEY = 'map_structure_v2';
    const CACHE_PROGRESS_KEY = 'user_progress_v2';
    const CACHE_TTL = 300; // 5 minutos

    /**
     * Gera o mapa otimizado para o usuário
     */
    public function getOptimizedMap(int $userId, ?int $requestedJourney = null): array
    {
        // 1. Cache da estrutura de fases (raramente muda)
        $structureKey = $this->getStructureCacheKey($userId);
        $phaseStructure = Cache::remember($structureKey, 3600, function() use ($userId) {
            return $this->buildPhaseStructure($userId);
        });

        if (empty($phaseStructure)) {
            return ['error' => 'Nenhuma estrutura de fases encontrada'];
        }

        // 2. Cache do progresso do usuário (muda frequentemente, cache menor)
        $progressKey = $this->getProgressCacheKey($userId);
        $userProgress = Cache::remember($progressKey, self::CACHE_TTL, function() use ($userId, $phaseStructure) {
            return $this->calculateUserProgress($userId, $phaseStructure);
        });

        // 3. Combinar estrutura + progresso de forma otimizada
        $optimizedPhases = $this->combineStructureWithProgress($phaseStructure, $userProgress);

        // 4. Detectar jornada atual e aplicar paginação
        $journeyInfo = $this->calculateJourneyInfo($optimizedPhases, $requestedJourney, $userProgress);

        // 5. Retornar apenas dados da jornada atual (não todas as fases)
        $journeyPhases = $this->getJourneyPhases($optimizedPhases, $journeyInfo['current']);

        // 6. Organizar em módulos otimizados
        $modules = $this->organizePhasesIntoModulesOptimized($journeyPhases);

        return [
            'phases' => $journeyPhases,
            'modules' => $modules,
            'journey' => $journeyInfo,
            'user' => $this->getOptimizedUserData(),
        ];
    }

    /**
     * Invalida cache quando o progresso do usuário muda
     */
    public function invalidateUserProgressCache(int $userId): void
    {
        $progressKey = $this->getProgressCacheKey($userId);
        Cache::forget($progressKey);

        // Também invalidar cache de estrutura se necessário
        $structureKey = $this->getStructureCacheKey($userId);
        Cache::forget($structureKey);
    }

    /**
     * Constrói a estrutura básica de fases (cacheable)
     */
    private function buildPhaseStructure(int $userId): array
    {
        $user = Auth::user();
        $legalReferences = $this->getUserLegalReferences($user);

        if ($legalReferences->isEmpty()) {
            return [];
        }

        $phaseStructureList = [];
        $lawChunksData = [];
        $maxChunks = 0;

        // Preparar chunks de todas as leis
        foreach ($legalReferences as $reference) {
            $chunks = $reference->articles->chunk(self::ARTICLES_PER_PHASE);
            $lawChunksData[$reference->uuid] = [
                'reference' => $reference,
                'chunks' => $chunks,
                'total_chunks' => $chunks->count()
            ];
            $maxChunks = max($maxChunks, $chunks->count());
        }

        // Intercalar fases de forma otimizada
        $tempCounter = 0;
        $totalModules = ceil($maxChunks / self::PHASES_PER_MODULE_PER_LAW);

        for ($moduleIndex = 0; $moduleIndex < $totalModules; $moduleIndex++) {
            $startChunkIndex = $moduleIndex * self::PHASES_PER_MODULE_PER_LAW;
            $endChunkIndex = $startChunkIndex + self::PHASES_PER_MODULE_PER_LAW;

            foreach ($lawChunksData as $lawUuid => $lawData) {
                $phasesAddedForThisLaw = 0;

                // Fases regulares
                for ($chunkIndex = $startChunkIndex; $chunkIndex < $endChunkIndex && $phasesAddedForThisLaw < self::PHASES_PER_MODULE_PER_LAW; $chunkIndex++) {
                    if ($chunkIndex < $lawData['total_chunks']) {
                        $tempCounter++;

                        $phaseStructureList[] = [
                            'id' => $tempCounter,
                            'is_review' => false,
                            'reference_uuid' => $lawUuid,
                            'reference_name' => $lawData['reference']->name,
                            'chunk_index' => $chunkIndex,
                            'article_ids' => $lawData['chunks'][$chunkIndex]->pluck('id')->toArray()
                        ];

                        $phasesAddedForThisLaw++;
                    }
                }

                // Fase de revisão
                if ($phasesAddedForThisLaw > 0) {
                    $totalRegularPhasesOfThisLaw = min($endChunkIndex, $lawData['total_chunks']);

                    if ($totalRegularPhasesOfThisLaw % self::REVIEW_PHASE_INTERVAL === 0) {
                        $tempCounter++;
                        $phaseStructureList[] = [
                            'id' => $tempCounter,
                            'is_review' => true,
                            'reference_uuid' => $lawUuid,
                            'reference_name' => $lawData['reference']->name,
                            'last_regular_counter' => $totalRegularPhasesOfThisLaw
                        ];
                    }
                }
            }
        }

        return $phaseStructureList;
    }

    /**
     * Calcula progresso do usuário de forma otimizada
     */
    private function calculateUserProgress(int $userId, array $phaseStructure): array
    {
        // Coletar todos os article_ids de uma vez
        $allArticleIds = collect();
        foreach ($phaseStructure as $phase) {
            if (!$phase['is_review'] && isset($phase['article_ids'])) {
                $allArticleIds = $allArticleIds->merge($phase['article_ids']);
            }
        }

        // Query única para todo o progresso
        $progressRecords = UserProgress::where('user_id', $userId)
            ->whereIn('law_article_id', $allArticleIds->unique())
            ->get()
            ->keyBy('law_article_id');

        $userProgress = [];
        $currentPhaseId = null;
        $blockSubsequent = false;
        $previousPhaseIsComplete = true;
        $currentLawUuid = null;
        $lawCompletionStatus = [];

        foreach ($phaseStructure as $phase) {
            $phaseId = $phase['id'];

            // Verificar mudança de lei
            if ($phase['reference_uuid'] !== $currentLawUuid) {
                $previousLawUuid = $currentLawUuid;
                $currentLawUuid = $phase['reference_uuid'];

                if ($previousLawUuid !== null && isset($lawCompletionStatus[$previousLawUuid]) && !$lawCompletionStatus[$previousLawUuid]) {
                    $blockSubsequent = true;
                }
                $lawCompletionStatus[$currentLawUuid] = true;
            }

            $isPhaseBlocked = $blockSubsequent || !$previousPhaseIsComplete;
            $isPhaseComplete = false;
            $isPhaseCurrent = false;

            if ($phase['is_review']) {
                // Progresso de revisão otimizado
                $articlesInScope = $this->getArticlesInScopeForReviewOptimized($phaseId, $phaseStructure);
                $incompleteCount = $progressRecords->whereIn('law_article_id', $articlesInScope)
                    ->where('percentage', '<', 100)->count();

                $isPhaseComplete = $incompleteCount === 0;

                $userProgress[$phaseId] = [
                    'is_complete' => $isPhaseComplete,
                    'needs_review' => !$isPhaseComplete,
                    'articles_to_review_count' => $incompleteCount,
                    'completed' => 0,
                    'total' => 0,
                    'has_errors' => false
                ];
            } else {
                // Progresso de fase regular otimizado
                $articleIds = $phase['article_ids'];
                $attempted = 0;
                $correct = 0;
                $hasErrors = false;

                foreach ($articleIds as $articleId) {
                    $progress = $progressRecords->get($articleId);
                    if ($progress) {
                        $attempted++;
                        if ($progress->percentage >= 100) {
                            $correct++;
                        } else {
                            $hasErrors = true;
                        }
                    }
                }

                $isPhaseComplete = $attempted === count($articleIds);

                $userProgress[$phaseId] = [
                    'completed' => $attempted,
                    'total' => count($articleIds),
                    'percentage' => count($articleIds) > 0 ? round(($attempted / count($articleIds)) * 100, 2) : 0,
                    'is_fully_complete' => $correct === count($articleIds),
                    'all_attempted' => $isPhaseComplete,
                    'has_errors' => $hasErrors
                ];
            }

            // Determinar fase atual
            if (!$isPhaseBlocked && !$isPhaseComplete && $currentPhaseId === null) {
                $isPhaseCurrent = true;
                $currentPhaseId = $phaseId;
            }

            $userProgress[$phaseId]['is_blocked'] = $isPhaseBlocked;
            $userProgress[$phaseId]['is_current'] = $isPhaseCurrent;
            $userProgress[$phaseId]['is_complete'] = $isPhaseComplete;

            // Atualizar status da lei
            if (!$isPhaseComplete) {
                $lawCompletionStatus[$currentLawUuid] = false;
            }

            $previousPhaseIsComplete = $isPhaseComplete;
            if ($isPhaseCurrent) {
                $blockSubsequent = true;
            }
        }

        return [
            'progress' => $userProgress,
            'current_phase_id' => $currentPhaseId
        ];
    }

    /**
     * Combina estrutura com progresso de forma otimizada
     */
    private function combineStructureWithProgress(array $phaseStructure, array $userProgress): array
    {
        $optimizedPhases = [];
        $progressData = $userProgress['progress'];

        foreach ($phaseStructure as $phase) {
            $phaseId = $phase['id'];
            $progress = $progressData[$phaseId] ?? [];

            // Dados mínimos necessários para o mapa
            $optimizedPhase = [
                'id' => $phaseId,
                'title' => $phase['is_review'] ?
                    'Revisão ' . ceil(($phase['last_regular_counter'] ?? 0) / self::REVIEW_PHASE_INTERVAL) :
                    'Fase ' . $phaseId,
                'reference_name' => $phase['reference_name'],
                'reference_uuid' => $phase['reference_uuid'],
                'is_review' => $phase['is_review'],
                'is_complete' => $progress['is_complete'] ?? false,
                'is_blocked' => $progress['is_blocked'] ?? false,
                'is_current' => $progress['is_current'] ?? false,
                // Progresso simplificado
                'progress' => [
                    'completed' => $progress['completed'] ?? 0,
                    'total' => $progress['total'] ?? 0,
                    'has_errors' => $progress['has_errors'] ?? false
                ]
            ];

            $optimizedPhases[] = $optimizedPhase;
        }

        return $optimizedPhases;
    }

    /**
     * Calcula informações da jornada
     */
    private function calculateJourneyInfo(array $optimizedPhases, ?int $requestedJourney, array $userProgress): array
    {
        $totalPhases = count($optimizedPhases);
        $totalJourneys = ceil($totalPhases / self::PHASES_PER_JOURNEY);
        $currentPhaseId = $userProgress['current_phase_id'];

        // Auto-detectar jornada se não especificada
        if ($requestedJourney === null && $currentPhaseId !== null) {
            $currentPhaseIndex = array_search($currentPhaseId, array_column($optimizedPhases, 'id'));
            if ($currentPhaseIndex !== false) {
                $detectedJourney = floor($currentPhaseIndex / self::PHASES_PER_JOURNEY) + 1;
                $currentJourney = $detectedJourney;
            } else {
                $currentJourney = 1;
            }
        } else {
            $currentJourney = max(1, min($requestedJourney ?? 1, $totalJourneys));
        }

        return [
            'current' => $currentJourney,
            'total' => $totalJourneys,
            'has_previous' => $currentJourney > 1,
            'has_next' => $currentJourney < $totalJourneys,
            'phases_in_journey' => min(self::PHASES_PER_JOURNEY, $totalPhases - (($currentJourney - 1) * self::PHASES_PER_JOURNEY)),
            'total_phases' => $totalPhases,
            'journey_title' => $totalJourneys > 1 ? "Jornada {$currentJourney} de {$totalJourneys}" : null,
            'current_phase_id' => $currentPhaseId,
            'was_auto_detected' => $requestedJourney === null
        ];
    }

    /**
     * Retorna apenas as fases da jornada atual
     */
    private function getJourneyPhases(array $optimizedPhases, int $currentJourney): array
    {
        $startIndex = ($currentJourney - 1) * self::PHASES_PER_JOURNEY;
        $endIndex = min($startIndex + self::PHASES_PER_JOURNEY, count($optimizedPhases));

        return array_slice($optimizedPhases, $startIndex, $endIndex - $startIndex);
    }

    /**
     * Organiza fases em módulos de forma otimizada (apenas referências)
     */
    private function organizePhasesIntoModulesOptimized(array $journeyPhases): array
    {
        if (empty($journeyPhases)) {
            return [];
        }

        // Se há apenas uma lei, não mostrar módulos
        $uniqueReferences = $this->getUniqueLegalReferences($journeyPhases);
        if (count($uniqueReferences) <= 1) {
            return [];
        }

        $modules = [];
        $moduleNumber = 1;

        // Agrupar por referência - OTIMIZADO (sem duplicar dados)
        $referenceGroups = [];
        foreach ($journeyPhases as $phase) {
            $uuid = $phase['reference_uuid'];
            if (!isset($referenceGroups[$uuid])) {
                $referenceGroups[$uuid] = [
                    'reference_name' => $phase['reference_name'],
                    'reference_uuid' => $uuid,
                    'phase_ids' => [] // Apenas IDs
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
        }

        return $modules;
    }

    /**
     * Dados do usuário otimizados
     */
    private function getOptimizedUserData(): array
    {
        $user = Auth::user();
        return [
            'lives' => $user->lives,
            'has_infinite_lives' => $user->hasInfiniteLives()
        ];
    }

    /**
     * Helpers privados
     */
    private function getStructureCacheKey(int $userId): string
    {
        $user = Auth::user();
        $preferencesHash = $user->legalReferences()->pluck('legal_references.id')->sort()->implode(',');
        return self::CACHE_STRUCTURE_KEY . "_{$userId}_" . md5($preferencesHash);
    }

    private function getProgressCacheKey(int $userId): string
    {
        return self::CACHE_PROGRESS_KEY . "_{$userId}";
    }

    private function getUserLegalReferences($user): Collection
    {
        $hasPreferences = $user->legalReferences()->exists();

        $query = LegalReference::with(['articles' => function($query) {
            $query->orderByRaw('CAST(article_reference AS UNSIGNED) ASC')->where('is_active', true);
        }]);

        if ($hasPreferences) {
            $query->whereHas('users', function($query) use ($user) {
                $query->where('users.id', $user->id);
            });
        } else {
            $query->where('is_active', true);
        }

        return $query->orderBy('id', 'asc')->get();
    }

    private function getArticlesInScopeForReviewOptimized(int $reviewPhaseId, array $phaseStructure): array
    {
        $articlesInScope = [];
        $referenceUuid = null;
        $currentReviewPhaseIndex = -1;

        // Encontrar fase de revisão
        foreach ($phaseStructure as $index => $phase) {
            if ($phase['id'] === $reviewPhaseId && $phase['is_review']) {
                $referenceUuid = $phase['reference_uuid'];
                $currentReviewPhaseIndex = $index;
                break;
            }
        }

        if ($currentReviewPhaseIndex === -1) {
            return [];
        }

        // Coletar artigos das fases regulares anteriores da mesma lei
        for ($i = $currentReviewPhaseIndex - 1; $i >= 0; $i--) {
            $phase = $phaseStructure[$i];

            if ($phase['reference_uuid'] !== $referenceUuid) {
                break; // Mudou de lei
            }

            if ($phase['is_review']) {
                break; // Encontrou revisão anterior
            }

            if (isset($phase['article_ids'])) {
                $articlesInScope = array_merge($articlesInScope, $phase['article_ids']);
            }
        }

        return array_unique($articlesInScope);
    }

    private function getUniqueLegalReferences(array $phases): array
    {
        $unique = [];
        foreach ($phases as $phase) {
            $uuid = $phase['reference_uuid'];
            if (!isset($unique[$uuid])) {
                $unique[$uuid] = [
                    'uuid' => $uuid,
                    'name' => $phase['reference_name']
                ];
            }
        }
        return array_values($unique);
    }
}