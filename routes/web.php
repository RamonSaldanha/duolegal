<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\LawArticleOptionController;
use App\Http\Controllers\PlayController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserLegalReferenceController;

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
        Route::get('/user/legal-references', [UserLegalReferenceController::class, 'index'])
            ->name('user.legal-references.index');
        Route::post('/user/legal-references', [UserLegalReferenceController::class, 'store'])
        ->name('user.legal-references.store');
        // Rotas de jogo (Play)
        Route::get('/play', [PlayController::class, 'map'])->name('play.map');
        Route::get('/play/no-lives', fn() => Inertia::render('Play/NoLives'))->name('play.nolives');
        Route::get('/play/{reference}/{phase}', [PlayController::class, 'phase'])->name('play.phase');
        Route::get('/play/review/{referenceUuid}/{phase}', action: [PlayController::class, 'review'])->name('play.review');

        Route::post('/play/progress', [PlayController::class, 'saveProgress'])->name('play.progress');
        Route::post('/play/reward-life', [PlayController::class, 'rewardLife'])->name('play.reward-life');

        // Rotas de assinatura
        Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
        Route::post('/subscription', [SubscriptionController::class, 'subscribe'])->name('subscription.subscribe');
        Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');
        Route::post('/subscription/resume', [SubscriptionController::class, 'resume'])->name('subscription.resume');
    });


// Rota para o webhook do Stripe - sem verificação CSRF
Route::post('/stripe/webhook', [\App\Http\Controllers\StripeWebhookController::class, 'handleWebhook'])
    ->middleware(['stripe'])
    ->withoutMiddleware(['web', 'csrf'])
    ->name('cashier.webhook');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
