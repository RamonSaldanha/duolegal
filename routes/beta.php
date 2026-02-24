<?php

use App\Http\Controllers\LegislationEditorController;
use App\Http\Controllers\LegislationPlayController;
use Illuminate\Support\Facades\Route;

// Rotas de jogo (qualquer usuário autenticado)
Route::middleware(['auth'])->prefix('beta')->group(function () {
    Route::get('map', [LegislationPlayController::class, 'map'])->name('beta.play.map');
    Route::get('map/phases', [LegislationPlayController::class, 'loadMapPhases'])->name('beta.map.phases');
    Route::post('map/filter', [LegislationPlayController::class, 'saveFilter'])->name('beta.map.filter');
    Route::get('preferences', [LegislationPlayController::class, 'preferences'])->name('beta.preferences');
    Route::post('preferences', [LegislationPlayController::class, 'savePreferences'])->name('beta.preferences.save');
    Route::get('play/phase/{phaseId}', [LegislationPlayController::class, 'playPhase'])->where('phaseId', '[0-9]+')->name('beta.play.phase');
    Route::get('play/{legislation:uuid}', [LegislationPlayController::class, 'play'])->name('beta.play.play');
    Route::get('play/{legislation:uuid}/completed', [LegislationPlayController::class, 'loadCompletedSegments'])->name('beta.play.completed');
    Route::post('play/submit', [LegislationPlayController::class, 'submitAnswer'])->name('beta.play.submit');
});

Route::middleware(['auth', 'admin'])->prefix('beta')->group(function () {
    // Lista de legislações
    Route::get('editor', [LegislationEditorController::class, 'index'])->name('beta.editor.index');

    // CRUD de legislação
    Route::post('editor', [LegislationEditorController::class, 'store'])->name('beta.editor.store');
    Route::get('editor/{legislation:uuid}', [LegislationEditorController::class, 'show'])->name('beta.editor.show');
    Route::put('editor/{legislation:uuid}', [LegislationEditorController::class, 'update'])->name('beta.editor.update');
    Route::delete('editor/{legislation:uuid}', [LegislationEditorController::class, 'destroy'])->name('beta.editor.destroy');

    // Fetch texto da URL (AJAX preview)
    Route::post('editor/fetch-text', [LegislationEditorController::class, 'fetchText'])->name('beta.editor.fetch-text');

    // Importar texto de URL para legislação existente
    Route::post('editor/{legislation:uuid}/import-text', [LegislationEditorController::class, 'importText'])->name('beta.editor.import-text');

    // Criar bloco a partir de posição no texto
    Route::post('editor/{legislation:uuid}/create-block', [LegislationEditorController::class, 'createBlockAtPosition'])->name('beta.editor.create-block');

    // Detectar limites de bloco (JSON para auto-criação progressiva)
    Route::post('editor/{legislation:uuid}/detect-boundaries', [LegislationEditorController::class, 'detectBoundaries'])->name('beta.editor.detect-boundaries');

    // Auto-criar todos os blocos (fallback)
    Route::post('editor/{legislation:uuid}/auto-create', [LegislationEditorController::class, 'autoCreate'])->name('beta.editor.auto-create');

    // Remover bloco (segmento)
    Route::delete('editor/segment/{legislationSegment:uuid}', [LegislationEditorController::class, 'removeBlock'])->name('beta.editor.remove-block');

    // Toggle lacuna por segmento
    Route::post('editor/segment/{legislationSegment:uuid}/toggle-lacuna', [LegislationEditorController::class, 'toggleLacuna'])->name('beta.editor.toggle-lacuna');

    // Distratores
    Route::post('editor/segment/{legislationSegment:uuid}/add-distractor', [LegislationEditorController::class, 'addDistractor'])->name('beta.editor.add-distractor');
    Route::delete('editor/lacuna/{segmentLacuna:uuid}', [LegislationEditorController::class, 'removeLacuna'])->name('beta.editor.remove-lacuna');
});
