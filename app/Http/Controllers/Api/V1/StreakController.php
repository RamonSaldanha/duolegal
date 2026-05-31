<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StreakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StreakController extends Controller
{
    public function __construct(private StreakService $streakService) {}

    /**
     * GET /v1/streak[?month=YYYY-MM]
     * Estatísticas de ofensiva do usuário autenticado (atual, recorde,
     * semana e calendário mensal).
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json(
            $this->streakService->getStats($request->user()->id, $request->query('month'))
        );
    }
}
