<?php

// routes\web.php
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [WelcomeController::class, 'index'])->name('home');

// === ROTAS PÚBLICAS PARA SEO ===
// Páginas públicas de leis e artigos (sem necessidade de login)
Route::get('/leis', [PublicController::class, 'index'])->name('public.laws');

// Legacy UUID routes with redirect middleware (must come first due to constraints)
Route::get('/leis/{uuid}', [PublicController::class, 'redirectLaw'])
    ->where('uuid', '[0-9a-f-]{36}')
    ->name('public.law.legacy');
Route::get('/leis/{lawUuid}/artigo/{articleUuid}', [PublicController::class, 'redirectArticle'])
    ->where(['lawUuid' => '[0-9a-f-]{36}', 'articleUuid' => '[0-9a-f-]{36}'])
    ->name('public.article.legacy');

// New slug-based routes (preferred)
Route::get('/leis/{legalReference:slug}', [PublicController::class, 'showLaw'])
    ->name('public.law');
Route::get('/leis/{legalReference:slug}/artigo/{article:slug}', [PublicController::class, 'showArticle'])
    ->name('public.article')
    ->scopeBindings();
Route::get('/buscar', [PublicController::class, 'search'])->name('public.search');

// === ROTAS DO SITEMAP PARA SEO ===
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-main.xml', [SitemapController::class, 'main'])->name('sitemap.main');
Route::get('/sitemap-laws.xml', [SitemapController::class, 'laws'])->name('sitemap.laws');
Route::get('/sitemap-articles.xml', [SitemapController::class, 'articles'])->name('sitemap.articles');

// Rotas das páginas legais
Route::get('/privacy-policy', function () {
    return Inertia::render('PrivacyPolicy');
})->name('privacy-policy');

Route::get('/cookies', function () {
    return Inertia::render('Cookies');
})->name('cookies');

Route::get('/terms', function () {
    return Inertia::render('Terms');
})->name('terms');

Route::get('dashboard', function () {
    return redirect()->route('play.map');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    // === ROTAS DE DESAFIOS ===
    // Desafios públicos
    Route::get('/challenges', [ChallengeController::class, 'index'])->name('challenges.index');
    Route::get('/challenges/create', [ChallengeController::class, 'create'])->name('challenges.create');
    Route::post('/challenges', [ChallengeController::class, 'store'])->name('challenges.store');

    // Meus desafios
    Route::get('/my-challenges', [ChallengeController::class, 'myIndex'])->name('challenges.my-index');

    // Detalhes e participação em desafios (usando model binding com uuid)
    Route::get('/challenges/{challenge:uuid}', [ChallengeController::class, 'show'])->name('challenges.show');
    Route::get('/challenges/{challenge:uuid}/edit', [ChallengeController::class, 'edit'])->name('challenges.edit');
    Route::put('/challenges/{challenge:uuid}', [ChallengeController::class, 'update'])->name('challenges.update');
    Route::delete('/challenges/{challenge:uuid}', [ChallengeController::class, 'destroy'])->name('challenges.destroy');

    // Participação nos desafios
    Route::post('/challenges/{challenge:uuid}/join', [ChallengeController::class, 'join'])->name('challenges.join');
    Route::get('/challenges/{challenge:uuid}/map', [ChallengeController::class, 'map'])->name('challenges.map');

    Route::get('/challenges/{challenge:uuid}/phase/{phaseNumber}', [ChallengeController::class, 'phase'])
        ->where('phaseNumber', '[0-9]+')
        ->name('challenges.phase');
    Route::post('/challenges/{challenge:uuid}/progress', [ChallengeController::class, 'saveProgress'])->name('challenges.progress');

    // Rotas de assinatura
    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('/subscription', [SubscriptionController::class, 'subscribe'])->name('subscription.subscribe');
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');
    Route::post('/subscription/resume', [SubscriptionController::class, 'resume'])->name('subscription.resume');
    Route::post('/subscription/validate-coupon', [SubscriptionController::class, 'validateCoupon'])->name('subscription.validate-coupon');

    // Rota de ranking
    Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');

    // Rota da loja
    Route::get('/store', function () {
        return \Inertia\Inertia::render('Store/Index');
    })->name('store.index');
});

// Rota para o webhook do Stripe - sem verificação CSRF
Route::post('/stripe/webhook', [\App\Http\Controllers\StripeWebhookController::class, 'handleWebhook'])
    ->middleware(['stripe'])
    ->withoutMiddleware(['web', 'csrf'])
    ->name('cashier.webhook');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/beta.php';
require __DIR__.'/legado.php';
