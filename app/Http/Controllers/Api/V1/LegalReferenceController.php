<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Legislation;
use App\Models\LegislationSegment;
use App\Models\UserLegislationSelection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalReferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $legislations = Legislation::where('status', 'published')
            ->orderBy('title')
            ->get();

        $selectedIds = UserLegislationSelection::where('user_id', $user->id)
            ->pluck('legislation_id')
            ->toArray();

        $legislationIds = $legislations->pluck('id')->toArray();

        $totalBlocks = LegislationSegment::whereIn('legislation_id', $legislationIds)
            ->where('is_block', true)
            ->selectRaw('legislation_id, COUNT(*) as total')
            ->groupBy('legislation_id')
            ->pluck('total', 'legislation_id')
            ->toArray();

        $completedBlocks = LegislationSegment::whereIn('legislation_segments.legislation_id', $legislationIds)
            ->where('legislation_segments.is_block', true)
            ->join('user_segment_progress', function ($join) use ($user) {
                $join->on('user_segment_progress.legislation_segment_id', '=', 'legislation_segments.id')
                    ->where('user_segment_progress.user_id', '=', $user->id)
                    ->where('user_segment_progress.is_completed', '=', true);
            })
            ->selectRaw('legislation_segments.legislation_id, COUNT(*) as completed')
            ->groupBy('legislation_segments.legislation_id')
            ->pluck('completed', 'legislation_id')
            ->toArray();

        $references = $legislations->map(function ($leg) use ($totalBlocks, $completedBlocks, $selectedIds) {
            $total = $totalBlocks[$leg->id] ?? 0;
            $completed = $completedBlocks[$leg->id] ?? 0;

            return [
                'id' => $leg->id,
                'uuid' => $leg->uuid,
                'title' => $leg->title,
                'total_blocks' => $total,
                'completed_blocks' => $completed,
                'percentage' => $total > 0 ? round(($completed / $total) * 100) : 0,
                'is_selected' => in_array($leg->id, $selectedIds),
            ];
        });

        return response()->json([
            'legal_references' => $references,
            'selected_ids' => $selectedIds,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:legislations,id',
        ]);

        $user = $request->user();

        UserLegislationSelection::where('user_id', $user->id)->delete();

        foreach ($request->ids as $legislationId) {
            UserLegislationSelection::create([
                'user_id' => $user->id,
                'legislation_id' => $legislationId,
            ]);
        }

        return response()->json([
            'message' => 'Preferências salvas com sucesso.',
            'selected_ids' => $request->ids,
        ]);
    }
}
