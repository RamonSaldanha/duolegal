<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DisciplineStarService;
use App\Services\XpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisciplineProgressController extends Controller
{
    public function __construct(
        private XpService $xpService,
        private DisciplineStarService $stars
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $totalXp = $user->xp;

        return response()->json([
            'discipline_progress' => $this->stars->progressFor($user),
            'total_xp' => $totalXp,
            'global_level' => $this->xpService->calculateLevel($totalXp),
        ]);
    }
}
