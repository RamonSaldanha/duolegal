<?php

// routes/legado.php — Rotas do sistema LEGADO (tradicional)
// Estas rotas mantêm o sistema antigo acessível sob o prefixo /legado

use App\Http\Controllers\LearnChallengeController;
use App\Http\Controllers\LearnController;
use App\Http\Controllers\PlayController;
use App\Http\Controllers\UserLegalReferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('legado')->group(function () {
    // Preferências de leis (sistema antigo)
    Route::get('/user/legal-references', [UserLegalReferenceController::class, 'index'])
        ->name('legado.user.legal-references.index');
    Route::post('/user/legal-references', [UserLegalReferenceController::class, 'store'])
        ->name('legado.user.legal-references.store');

    // Mapa de jogo (sistema antigo)
    Route::get('/play', [PlayController::class, 'map'])->name('legado.play.map');

    // Mapa de aprendizado otimizado (sistema antigo)
    Route::get('/learn', [LearnController::class, 'map'])->name('legado.learn.map');

    // Fases de jogo (sistema antigo)
    Route::get('/play/phase/{phaseId}', [PlayController::class, 'phase'])
        ->where('phaseId', '[0-9]+')
        ->name('legado.play.phase');

    // Revisão (sistema antigo)
    Route::get('/play/review/{referenceUuid}/{phase}', [PlayController::class, 'review'])
        ->where('phase', '[0-9]+')
        ->name('legado.play.review');

    Route::post('/play/progress', [PlayController::class, 'saveProgress'])->name('legado.play.progress');
    Route::post('/play/reward-life', [PlayController::class, 'rewardLife'])->name('legado.play.reward-life');

    // Mapa de desafios otimizado (sistema antigo)
    Route::get('/learn/challenges/{challenge:uuid}/map', [LearnChallengeController::class, 'map'])->name('legado.learn.challenges.map');
});
