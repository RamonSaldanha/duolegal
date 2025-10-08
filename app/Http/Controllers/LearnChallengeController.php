<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\ChallengeProgress;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LearnChallengeController extends Controller
{
    // Usar as mesmas constantes para consistência
    const ARTICLES_PER_PHASE = 6;

    const REVIEW_PHASE_INTERVAL = 3;

    const PHASES_PER_MODULE_PER_LAW = 6;

    /**
     * Mapa otimizado do desafio (sem payload pesado)
     */
    public function map(Challenge $challenge)
    {
        if (! $challenge->canAccess(Auth::user())) {
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

        // Agrupar artigos por referência legal
        $legalReferencesData = [];
        foreach ($challengeArticles as $article) {
            $refUuid = $article->legalReference->uuid;
            if (! isset($legalReferencesData[$refUuid])) {
                $legalReferencesData[$refUuid] = [
                    'reference' => $article->legalReference,
                    'articles' => collect(),
                ];
            }
            $legalReferencesData[$refUuid]['articles']->push($article);
        }

        // === PASSO 1: Construir estrutura de fases INTERCALADAS ===
        $phaseStructureList = [];
        $lawChunksData = [];
        $maxChunks = 0;

        foreach ($legalReferencesData as $refUuid => $refData) {
            $chunks = $refData['articles']->chunk(self::ARTICLES_PER_PHASE);
            $lawChunksData[$refUuid] = [
                'reference' => $refData['reference'],
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

        // === PASSO 2: Calcular progresso e bloqueios (versão otimizada) ===
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

                if ($previousLawUuid !== null && isset($lawCompletionStatus[$previousLawUuid]) && ! $lawCompletionStatus[$previousLawUuid]) {
                    $blockSubsequent = true;
                }
                $lawCompletionStatus[$currentLawUuid] = true;
            }

            $isPhaseBlocked = $blockSubsequent || ! $previousPhaseIsComplete;
            $isPhaseCurrent = false;
            $isPhaseComplete = false;

            if ($phaseStruct['is_review']) {
                // Fase de revisão
                $articleIdsInScope = $this->getChallengeArticlesInScopeForReview($currentPhaseGlobalId, $phaseStructureList, $challenge->id);
                $phaseProgress = $this->getChallengeReviewPhaseProgress($userId, $challenge->id, $articleIdsInScope);
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
                $phaseProgress = $this->getChallengePhaseProgress($userId, $challenge->id, $phaseStruct['article_chunk']);
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

        // Organizar em módulos otimizados
        $allReferences = collect($legalReferencesData)->map(fn ($data) => $data['reference']);
        $modulesData = $this->organizePhasesIntoModulesOptimized($phasesData, $allReferences);

        // Obter usuários por fase (apenas para desafios)
        $usersPerPhase = $this->getChallengeUsersPerPhase($challenge, $phaseStructureList);

        return Inertia::render('Learn/Map', [
            'phases' => $phasesData,
            'modules' => $modulesData,
            'journey' => null, // Desafios não usam jornadas
            'user' => [
                'lives' => $user->lives,
                'has_infinite_lives' => $user->hasInfiniteLives(),
            ],
            'is_challenge' => true,
            'challenge' => [
                'uuid' => $challenge->uuid,
                'title' => $challenge->title,
                'description' => $challenge->description,
            ],
            'users_per_phase' => $usersPerPhase,
        ]);
    }

    // ===== MÉTODOS OTIMIZADOS =====

    private function buildOptimizedPhaseData(
        int $phaseId,
        string $referenceUuid,
        bool $isBlocked,
        bool $isCurrent,
        bool $isComplete
    ): array {
        return [
            'id' => $phaseId,
            'reference_uuid' => $referenceUuid,
            'is_blocked' => $isBlocked,
            'is_current' => $isCurrent,
            'is_complete' => $isComplete,
            'is_review' => false,
        ];
    }

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
            'progress' => [
                'needs_review' => $phaseProgress['needs_review'] ?? false,
                'articles_to_review_count' => $phaseProgress['articles_to_review_count'] ?? 0,
            ],
        ];
    }

    private function organizePhasesIntoModulesOptimized(array $phasesData, $legalReferences): array
    {
        if (empty($phasesData)) {
            return [];
        }

        $uniqueRefs = [];
        foreach ($phasesData as $phase) {
            $uniqueRefs[$phase['reference_uuid']] = true;
        }

        if (count($uniqueRefs) <= 1) {
            return [];
        }

        $modules = [];
        $moduleNumber = 1;
        $totalPhases = count($phasesData);
        $phasesPerModule = 12;

        for ($startIndex = 0; $startIndex < $totalPhases; $startIndex += $phasesPerModule) {
            $endIndex = min($startIndex + $phasesPerModule, $totalPhases);
            $modulePhasesSlice = array_slice($phasesData, $startIndex, $endIndex - $startIndex);

            if (empty($modulePhasesSlice)) {
                continue;
            }

            $referenceGroups = [];
            foreach ($modulePhasesSlice as $phase) {
                $uuid = $phase['reference_uuid'];
                if (! isset($referenceGroups[$uuid])) {
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

    // ===== MÉTODOS AUXILIARES =====

    private function getChallengePhaseProgress($userId, $challengeId, $articles)
    {
        if (! $userId || $articles->isEmpty()) {
            return [
                'all_attempted' => false,
            ];
        }

        $articleIds = $articles->pluck('id');
        $progressRecords = ChallengeProgress::where('user_id', $userId)
            ->where('challenge_id', $challengeId)
            ->whereIn('law_article_id', $articleIds)
            ->get()
            ->keyBy('law_article_id');

        $totalArticles = $articleIds->count();
        $attemptedCount = 0;

        foreach ($articles as $article) {
            $progress = $progressRecords->get($article->id);
            if ($progress) {
                $attemptedCount++;
            }
        }

        $allAttempted = ($attemptedCount === $totalArticles);

        return [
            'all_attempted' => $allAttempted,
        ];
    }

    private function getChallengeReviewPhaseProgress($userId, $challengeId, $articleIdsInScope)
    {
        if (! $userId || $articleIdsInScope->isEmpty()) {
            return [
                'is_complete' => true,
                'needs_review' => false,
                'articles_to_review_count' => 0,
            ];
        }

        $incompleteArticlesCount = ChallengeProgress::where('user_id', $userId)
            ->where('challenge_id', $challengeId)
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

    private function getChallengeArticlesInScopeForReview($reviewPhaseId, $phaseStructureList, $challengeId)
    {
        $articleIds = collect();
        $referenceUuid = null;
        $currentReviewPhaseIndex = -1;

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
            if (isset($phaseStructureList[$i]) && ! $phaseStructureList[$i]['is_review']) {
                $phase = $phaseStructureList[$i];
                if (isset($phase['article_chunk'])) {
                    $chunkArticleIds = $phase['article_chunk']->pluck('id');
                    $articleIds = $articleIds->merge($chunkArticleIds);
                }
            }
        }

        return $articleIds->unique();
    }

    private function getChallengeUsersPerPhase($challenge, $phaseStructureList)
    {
        $challengeProgress = ChallengeProgress::where('challenge_id', $challenge->id)
            ->with(['user' => function ($query) {
                $query->select('id', 'name');
            }])
            ->get();

        $articleToPhaseMap = [];
        foreach ($phaseStructureList as $phaseStruct) {
            if (! $phaseStruct['is_review'] && isset($phaseStruct['article_chunk'])) {
                foreach ($phaseStruct['article_chunk'] as $article) {
                    $articleToPhaseMap[$article->id] = $phaseStruct['id'];
                }
            }
        }

        $usersPerPhase = [];

        foreach ($challengeProgress->groupBy('user_id') as $userId => $userProgress) {
            $user = $userProgress->first()->user;
            if (! $user) {
                continue;
            }

            $progressByPhase = [];
            foreach ($userProgress as $progress) {
                $articleId = $progress->law_article_id;
                $phaseId = $articleToPhaseMap[$articleId] ?? null;

                if ($phaseId) {
                    if (! isset($progressByPhase[$phaseId])) {
                        $progressByPhase[$phaseId] = [
                            'attempted_articles' => 0,
                            'total_articles' => 0,
                        ];
                    }

                    $progressByPhase[$phaseId]['total_articles']++;

                    if ($progress->attempts > 0) {
                        $progressByPhase[$phaseId]['attempted_articles']++;
                    }
                }
            }

            $currentPhase = null;
            $blockSubsequent = false;
            $previousPhaseIsComplete = true;
            $currentLawUuid = null;
            $lawCompletionStatus = [];

            foreach ($phaseStructureList as $phaseStruct) {
                $phaseId = $phaseStruct['id'];

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

                if ($phaseStruct['is_review']) {
                    $phaseProgress = $progressByPhase[$phaseId] ?? null;
                    if ($phaseProgress) {
                        $isPhaseComplete = ($phaseProgress['attempted_articles'] === $phaseProgress['total_articles']);
                    } else {
                        $isPhaseComplete = true;
                    }
                } else {
                    $phaseProgress = $progressByPhase[$phaseId] ?? null;
                    if ($phaseProgress) {
                        $articleChunk = $phaseStruct['article_chunk'] ?? collect();
                        $realPhaseProgress = $this->getChallengePhaseProgress($userId, $challenge->id, $articleChunk);
                        $isPhaseComplete = $realPhaseProgress['all_attempted'];
                    } else {
                        $isPhaseComplete = false;
                    }
                }

                if (! $isPhaseBlocked && ! $isPhaseComplete && $currentPhase === null) {
                    $currentPhase = $phaseId;
                    $blockSubsequent = true;
                }

                if (! $isPhaseComplete) {
                    $lawCompletionStatus[$currentLawUuid] = false;
                }

                $previousPhaseIsComplete = $isPhaseComplete;
            }

            if (! $currentPhase) {
                continue;
            }

            if (! isset($usersPerPhase[$currentPhase])) {
                $usersPerPhase[$currentPhase] = [];
            }

            $usersPerPhase[$currentPhase][] = [
                'id' => $user->id,
                'name' => $user->name,
            ];
        }

        return $usersPerPhase;
    }
}
