<?php

namespace App\Services;

use App\Models\Legislation;
use App\Models\User;
use App\Models\UserSegmentProgress;

class PhaseGenerationService
{
    public const BLOCKS_PER_PHASE = 15;

    public const PHASES_PER_LEGISLATION_SWITCH = 4;

    public const PHASES_PER_PAGE = 30;

    /**
     * Gera a lista intercalada de fases a partir das legislações selecionadas.
     * Cada fase inclui: id, legislation_uuid, legislation_title, block_ids,
     * block_count, show_legislation_header, is_complete, is_blocked, is_current, progress.
     */
    public function generatePhaseList(User $user, array $selectedUuids = []): array
    {
        $query = Legislation::where('status', 'published')
            ->with(['segments' => fn ($q) => $q->where('is_block', true)->orderBy('char_start')]);

        if (! empty($selectedUuids)) {
            $query->whereIn('uuid', $selectedUuids);
        }

        $legislations = $query->orderBy('id')->get();

        if ($legislations->isEmpty()) {
            return [];
        }

        $lawChunks = [];
        foreach ($legislations as $leg) {
            $chunks = $leg->segments->chunk(self::BLOCKS_PER_PHASE)->values();
            if ($chunks->isNotEmpty()) {
                $lawChunks[$leg->uuid] = [
                    'legislation' => $leg,
                    'chunks' => $chunks,
                    'cursor' => 0,
                ];
            }
        }

        if (empty($lawChunks)) {
            return [];
        }

        $phaseList = [];
        $phaseId = 0;
        $prevLegUuid = null;

        while (true) {
            $addedAny = false;

            foreach ($lawChunks as $uuid => &$data) {
                $added = 0;

                while ($added < self::PHASES_PER_LEGISLATION_SWITCH && $data['cursor'] < $data['chunks']->count()) {
                    $phaseId++;
                    $chunk = $data['chunks'][$data['cursor']];

                    $phaseList[] = [
                        'id' => $phaseId,
                        'legislation_uuid' => $uuid,
                        'legislation_title' => $data['legislation']->title,
                        'block_ids' => $chunk->pluck('id')->toArray(),
                        'block_count' => $chunk->count(),
                        'show_legislation_header' => ($uuid !== $prevLegUuid),
                    ];

                    $prevLegUuid = $uuid;
                    $data['cursor']++;
                    $added++;
                    $addedAny = true;
                }
            }
            unset($data);

            if (! $addedAny) {
                break;
            }
        }

        $allBlockIds = collect($phaseList)->pluck('block_ids')->flatten()->unique()->toArray();

        $allProgress = UserSegmentProgress::where('user_id', $user->id)
            ->whereIn('legislation_segment_id', $allBlockIds)
            ->get()
            ->keyBy('legislation_segment_id');

        $previousComplete = true;
        $currentFound = false;

        foreach ($phaseList as &$phase) {
            $completedCount = 0;
            $blockStatus = [];

            foreach ($phase['block_ids'] as $blockId) {
                $progress = $allProgress->get($blockId);

                if ($progress && $progress->is_completed) {
                    $completedCount++;
                    $blockStatus[] = 'correct';
                } elseif ($progress && $progress->attempts > 0) {
                    $blockStatus[] = 'incorrect';
                } else {
                    $blockStatus[] = 'pending';
                }
            }

            $total = $phase['block_count'];
            $isComplete = $completedCount >= $total && $total > 0;

            $phase['is_complete'] = $isComplete;
            $phase['is_blocked'] = $currentFound || ! $previousComplete;
            $phase['is_current'] = ! $phase['is_blocked'] && ! $isComplete && ! $currentFound;

            if ($phase['is_current']) {
                $currentFound = true;
            }

            $phase['progress'] = [
                'completed' => $completedCount,
                'total' => $total,
                'percentage' => $total > 0 ? round(($completedCount / $total) * 100) : 0,
                'block_status' => $blockStatus,
            ];

            $previousComplete = $isComplete;
        }
        unset($phase);

        return $phaseList;
    }

    /**
     * Remove block_ids antes de enviar para o frontend.
     */
    public function stripBlockIds(array $phase): array
    {
        unset($phase['block_ids']);

        return $phase;
    }
}
