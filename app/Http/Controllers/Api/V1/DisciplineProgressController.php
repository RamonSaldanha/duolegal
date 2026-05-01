<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Discipline;
use App\Services\XpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisciplineProgressController extends Controller
{
    public function __construct(private XpService $xpService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $disciplineProgress = $this->xpService->getUserDisciplineXp($user->id);
        $allDisciplines = Discipline::orderBy('name')->get();
        $existingIds = $disciplineProgress->pluck('id')->toArray();

        foreach ($allDisciplines as $discipline) {
            if (! in_array($discipline->id, $existingIds)) {
                $levelData = $this->xpService->calculateLevel(0);
                $disciplineProgress->push((object) array_merge([
                    'id' => $discipline->id,
                    'uuid' => $discipline->uuid,
                    'name' => $discipline->name,
                    'slug' => $discipline->slug,
                    'description' => $discipline->description,
                    'icon' => $discipline->icon,
                    'color' => $discipline->color,
                    'total_xp' => 0,
                ], $levelData));
            }
        }

        $disciplineMap = $allDisciplines->keyBy('id');
        $disciplineProgress = $disciplineProgress->map(function ($item) use ($disciplineMap) {
            $obj = (array) $item;
            if (! isset($obj['icon']) || ! isset($obj['color'])) {
                $disc = $disciplineMap[$obj['id']] ?? null;
                if ($disc) {
                    $obj['icon'] = $disc->icon;
                    $obj['color'] = $disc->color;
                }
            }

            return $obj;
        });

        $totalXp = $user->xp;
        $globalLevel = $this->xpService->calculateLevel($totalXp);

        return response()->json([
            'discipline_progress' => $disciplineProgress->sortBy('name')->values(),
            'total_xp' => $totalXp,
            'global_level' => $globalLevel,
        ]);
    }
}
