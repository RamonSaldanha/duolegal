<?php

namespace App\Http\Controllers;

use App\Services\StreakService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StreakController extends Controller
{
    public function __construct(private StreakService $streakService) {}

    /**
     * Tela de Ofensiva (web): dias consecutivos, recorde, semana e calendário mensal.
     */
    public function index(Request $request): Response
    {
        return Inertia::render(
            'Ofensiva/Index',
            $this->streakService->getStats($request->user()->id, $request->query('month'))
        );
    }
}
