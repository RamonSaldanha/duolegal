<?php

namespace App\Http\Controllers;

use App\Models\Discipline;
use App\Services\XpService;
use Inertia\Inertia;
use Inertia\Response;

class DisciplineProgressController extends Controller
{
    public function __construct(
        private XpService $xpService
    ) {}

    public function index(): Response
    {
        $user = auth()->user();

        $disciplineProgress = $this->xpService->getUserDisciplineXp($user->id);

        // Incluir disciplinas que o usuário ainda não tem XP
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

        // Garantir que disciplinas com XP também tenham icon e color
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

            return (object) $obj;
        });

        $totalXp = $user->xp;
        $globalLevel = $this->xpService->calculateLevel($totalXp);

        return Inertia::render('Disciplines/Index', [
            'disciplineProgress' => $disciplineProgress->sortBy('name')->values(),
            'totalXp' => $totalXp,
            'globalLevel' => $globalLevel,
        ]);
    }
}
