<?php

namespace App\Http\Controllers;

use App\Services\DisciplineStarService;
use App\Services\XpService;
use Inertia\Inertia;
use Inertia\Response;

class DisciplineProgressController extends Controller
{
    public function __construct(
        private XpService $xpService,
        private DisciplineStarService $stars
    ) {}

    public function index(): Response
    {
        $user = auth()->user();
        $totalXp = $user->xp;

        return Inertia::render('Disciplines/Index', [
            'disciplineProgress' => $this->stars->progressFor($user),
            'totalXp' => $totalXp,
            'globalLevel' => $this->xpService->calculateLevel($totalXp),
        ]);
    }
}
