<?php
// routes\web.php
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\LawArticleOptionController;
use App\Http\Controllers\PlayController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserLegalReferenceController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\RankingController;

Route::get('/', [WelcomeController::class, 'index'])->name('home');

// === ROTAS PÚBLICAS PARA SEO ===
// Páginas públicas de leis e artigos (sem necessidade de login)
Route::get('/leis', [PublicController::class, 'index'])->name('public.laws');
Route::get('/leis/{uuid}', [PublicController::class, 'showLaw'])
    ->where('uuid', '[0-9a-f-]{36}')
    ->name('public.law');
Route::get('/leis/{lawUuid}/artigo/{articleUuid}', [PublicController::class, 'showArticle'])
    ->where(['lawUuid' => '[0-9a-f-]{36}', 'articleUuid' => '[0-9a-f-]{36}'])
    ->name('public.article');
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
        // ROTA MODIFICADA: Aceita apenas o ID global da fase
        Route::get('/play/phase/{phaseId}', [PlayController::class, 'phase'])
        ->where('phaseId', '[0-9]+') // Garante que phaseId seja numérico
        ->name('play.phase'); // Manter o nome da rota, se preferir

        // Rota de revisão continua aceitando UUID e phase ID (número global)
        Route::get('/play/review/{referenceUuid}/{phase}', [PlayController::class, 'review'])
        ->where('phase', '[0-9]+') // Garante que phase (ID global) seja numérico
        ->name('play.review');

        Route::post('/play/progress', [PlayController::class, 'saveProgress'])->name('play.progress');
        Route::post('/play/reward-life', [PlayController::class, 'rewardLife'])->name('play.reward-life');

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

        // Rota de ranking
        Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');
    });


// Rota para o webhook do Stripe - sem verificação CSRF
Route::post('/stripe/webhook', [\App\Http\Controllers\StripeWebhookController::class, 'handleWebhook'])
    ->middleware(['stripe'])
    ->withoutMiddleware(['web', 'csrf'])
    ->name('cashier.webhook');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
