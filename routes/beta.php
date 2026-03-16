<?php

use App\Http\Controllers\LegislationEditorController;
use App\Http\Controllers\LegislationPlayController;
use App\Http\Controllers\PlayController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Rotas de jogo — Sistema principal (Legislation)
Route::middleware(['auth'])->prefix('play')->group(function () {
    Route::get('/', [LegislationPlayController::class, 'map'])->name('play.map');
    Route::get('map/phases', [LegislationPlayController::class, 'loadMapPhases'])->name('play.map.phases');
    Route::post('map/filter', [LegislationPlayController::class, 'saveFilter'])->name('play.map.filter');
    Route::get('preferences', [LegislationPlayController::class, 'preferences'])->name('play.preferences');
    Route::post('preferences', [LegislationPlayController::class, 'savePreferences'])->name('play.preferences.save');
    Route::get('phase/{phaseId}', [LegislationPlayController::class, 'playPhase'])->where('phaseId', '[0-9]+')->name('play.phase');
    Route::get('legislation/{legislation:uuid}', [LegislationPlayController::class, 'play'])->name('play.legislation');
    Route::get('legislation/{legislation:uuid}/completed', [LegislationPlayController::class, 'loadCompletedSegments'])->name('play.completed');
    Route::post('submit', [LegislationPlayController::class, 'submitAnswer'])->name('play.submit');

    // Rotas compartilhadas entre sistemas
    Route::get('no-lives', fn () => Inertia::render('Play/NoLives'))->name('play.nolives');
    Route::post('reward-life', [PlayController::class, 'rewardLife'])->name('play.reward-life');
});

// Rotas do editor de legislações (admin)
Route::middleware(['auth', 'admin'])->prefix('editor')->group(function () {
    Route::get('/', [LegislationEditorController::class, 'index'])->name('editor.index');

    // CRUD de legislação
    Route::post('/', [LegislationEditorController::class, 'store'])->name('editor.store');
    Route::get('{legislation:uuid}', [LegislationEditorController::class, 'show'])->name('editor.show');
    Route::put('{legislation:uuid}', [LegislationEditorController::class, 'update'])->name('editor.update');
    Route::delete('{legislation:uuid}', [LegislationEditorController::class, 'destroy'])->name('editor.destroy');

    // Fetch texto da URL (AJAX preview)
    Route::post('fetch-text', [LegislationEditorController::class, 'fetchText'])->name('editor.fetch-text');

    // Importar texto de URL para legislação existente
    Route::post('{legislation:uuid}/import-text', [LegislationEditorController::class, 'importText'])->name('editor.import-text');

    // Criar bloco a partir de posição no texto
    Route::post('{legislation:uuid}/create-block', [LegislationEditorController::class, 'createBlockAtPosition'])->name('editor.create-block');

    // Detectar limites de bloco (JSON para auto-criação progressiva)
    Route::post('{legislation:uuid}/detect-boundaries', [LegislationEditorController::class, 'detectBoundaries'])->name('editor.detect-boundaries');

    // Auto-criar todos os blocos (fallback)
    Route::post('{legislation:uuid}/auto-create', [LegislationEditorController::class, 'autoCreate'])->name('editor.auto-create');

    // Remover bloco (segmento)
    Route::delete('segment/{legislationSegment:uuid}', [LegislationEditorController::class, 'removeBlock'])->name('editor.remove-block');

    // Toggle lacuna por segmento
    Route::post('segment/{legislationSegment:uuid}/toggle-lacuna', [LegislationEditorController::class, 'toggleLacuna'])->name('editor.toggle-lacuna');

    // Distratores
    Route::post('segment/{legislationSegment:uuid}/add-distractor', [LegislationEditorController::class, 'addDistractor'])->name('editor.add-distractor');
    Route::delete('lacuna/{segmentLacuna:uuid}', [LegislationEditorController::class, 'removeLacuna'])->name('editor.remove-lacuna');
});
