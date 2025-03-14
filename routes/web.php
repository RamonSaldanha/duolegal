<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\LawArticleOptionController;
use App\Http\Controllers\PlayController;
use App\Http\Controllers\GameController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Rotas para law-article-options
Route::get('/admin/law-article-options', [LawArticleOptionController::class, 'index'])
    ->name('admin.law-article-options.index');

// Adicionar esta linha para a nova rota de store
Route::post('/admin/law-article-options', [LawArticleOptionController::class, 'store'])
    ->name('admin.law-article-options.store');
    
    // Adicione esta rota ao seu arquivo de rotas
    Route::middleware(['auth'])->group(function () {
    
    // Rotas de jogo (Play)
    Route::get('/play', [PlayController::class, 'map'])->name('play.map');
    Route::get('/play/no-lives', fn() => Inertia::render('Play/NoLives'))->name('play.nolives');
    Route::get('/play/{reference}/{phase}', [PlayController::class, 'phase'])->name('play.phase');
    Route::post('/play/progress', [PlayController::class, 'saveProgress'])->name('play.progress');
    Route::post('/play/reward-life', [PlayController::class, 'rewardLife'])->name('play.reward-life');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
