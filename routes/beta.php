<?php

use App\Http\Controllers\BetaCheckpointController;
use App\Http\Controllers\BetaExamController;
use App\Http\Controllers\BetaPlayController;
use App\Http\Controllers\LegislationEditorController;
use App\Http\Controllers\LegislationPlayController;
use App\Http\Controllers\PlayController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// O mapa (quadrados com várias fases, energia, desafio do quadrado e Dia da prova): o padrão da
// web desde 22/11/2026, o mesmo do app. Foi o "mapa beta" (só administradores, em /beta/play);
// os nomes das rotas continuam beta.play.*, menos o do mapa, que é o play.map.
Route::middleware(['auth'])->prefix('play')->group(function () {
    Route::get('/', [BetaPlayController::class, 'betaMap'])->name('play.map');
    Route::get('phase/{phaseId}', [BetaPlayController::class, 'betaPhase'])->where('phaseId', '[0-9]+')->name('beta.play.phase');
    Route::post('attempt', [BetaPlayController::class, 'betaAttempt'])->name('beta.play.attempt');
    Route::post('finish', [BetaPlayController::class, 'betaFinish'])->name('beta.play.finish');

    // Energia: a tela de quando ela acaba e o anúncio que recarrega
    Route::get('energia', [BetaPlayController::class, 'betaEnergy'])->name('beta.play.energy');
    Route::post('energia/anuncio', [BetaPlayController::class, 'betaEnergyReward'])->middleware('throttle:12,60')->name('beta.play.energy.reward');

    // Desafio do quadrado: o último passo do quadrado, com o resumo e as questões dele
    Route::get('desafio/{checkpoint}', [BetaCheckpointController::class, 'show'])->whereUuid('checkpoint')->name('beta.play.checkpoint');
    Route::post('desafio/{checkpoint}/answer', [BetaCheckpointController::class, 'answer'])->whereUuid('checkpoint')->name('beta.play.checkpoint.answer');
    Route::post('desafio/{checkpoint}/summary', [BetaCheckpointController::class, 'summary'])->whereUuid('checkpoint')->name('beta.play.checkpoint.summary');

    // Dia da prova: fase paralela ao fim da etapa, com tempo, só com as questões dos desafios dela
    Route::get('prova/{exam}', [BetaExamController::class, 'show'])->whereUuid('exam')->name('beta.play.exam');
    Route::post('prova/{exam}/start', [BetaExamController::class, 'start'])->whereUuid('exam')->name('beta.play.exam.start');
    Route::post('prova/{exam}/answer', [BetaExamController::class, 'answer'])->whereUuid('exam')->name('beta.play.exam.answer');
    Route::post('prova/{exam}/finish', [BetaExamController::class, 'finish'])->whereUuid('exam')->name('beta.play.exam.finish');

    // As legislações que a pessoa estuda (aba "Leis")
    Route::get('preferences', [LegislationPlayController::class, 'preferences'])->name('play.preferences');
    Route::post('preferences', [LegislationPlayController::class, 'savePreferences'])->name('play.preferences.save');
});

// Prévia das telas de fim de fase e do convite Premium (só administradores)
Route::middleware(['auth', 'admin'])->get('play/previa/fim', fn () => Inertia::render('BetaPlay/PreviewEnd'))->name('beta.play.preview.end');

// Endereços antigos do mapa beta
Route::middleware(['auth'])->get('beta/play/{path?}', fn (?string $path = null) => redirect('/play'.($path ? '/'.$path : '')))->where('path', '.*');

// O mapa anterior (por legislação, com vidas), aposentado em 22/11/2026: fora do menu, só pelo endereço
Route::middleware(['auth'])->prefix('legado/leis')->group(function () {
    Route::get('/', [LegislationPlayController::class, 'map'])->name('legado.leis.map');
    Route::get('map/phases', [LegislationPlayController::class, 'loadMapPhases'])->name('play.map.phases');
    Route::post('map/filter', [LegislationPlayController::class, 'saveFilter'])->name('play.map.filter');
    Route::get('phase/{phaseId}', [LegislationPlayController::class, 'playPhase'])->where('phaseId', '[0-9]+')->name('play.phase');
    Route::get('legislation/{legislation:uuid}', [LegislationPlayController::class, 'play'])->name('play.legislation');
    Route::get('legislation/{legislation:uuid}/completed', [LegislationPlayController::class, 'loadCompletedSegments'])->name('play.completed');
    Route::post('submit', [LegislationPlayController::class, 'submitAnswer'])->name('play.submit');
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

    // Criar blocos em lote (JSON, usado pela criação automática)
    Route::post('{legislation:uuid}/blocks', [LegislationEditorController::class, 'storeBlocks'])->name('editor.store-blocks');

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
