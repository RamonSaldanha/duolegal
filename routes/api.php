<?php

use App\Http\Controllers\Api\LawArticleApiController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PlayController;
use App\Http\Controllers\Api\V1\RankingController;
use App\Http\Controllers\Api\V1\DisciplineProgressController;
use App\Http\Controllers\Api\V1\LegalReferenceController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Admin\LegalReferenceController as AdminLegalReferenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rotas públicas legadas
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('n8n/legal-references', [AdminLegalReferenceController::class, 'storeFromN8n']);

Route::prefix('law-articles')->group(function () {
    Route::post('/', [LawArticleApiController::class, 'store'])->name('api.law-articles.store');
    Route::get('/legal-references', [LawArticleApiController::class, 'getLegalReferences'])->name('api.law-articles.legal-references');
});

// === API V1 ===

// Auth (sem middleware)
Route::prefix('v1/auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::post('/email/resend-verification', [AuthController::class, 'resendVerification'])->middleware('auth:sanctum');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// Rotas autenticadas V1
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);

    // Gameplay
    Route::get('/play/map', [PlayController::class, 'map']);
    Route::get('/play/map/more', [PlayController::class, 'loadMapPhases']);
    Route::get('/play/phases/{phaseId}', [PlayController::class, 'phase']);
    Route::get('/play/phase/{phaseId}/more-above', [PlayController::class, 'loadMoreSegments']);
    Route::get('/play/review/{referenceUuid}/{phaseId}', [PlayController::class, 'review']);
    Route::post('/play/submit', [PlayController::class, 'submitAnswer']);
    Route::post('/play/reward-life', [PlayController::class, 'rewardLife']);
    Route::get('/play/preferences', [PlayController::class, 'preferences']);
    Route::put('/play/preferences', [PlayController::class, 'savePreferences']);

    // Ranking
    Route::get('/ranking', [RankingController::class, 'index']);

    // Conquistas / Disciplinas
    Route::get('/disciplines', [DisciplineProgressController::class, 'index']);

    // Legislações preferidas (legado)
    Route::get('/legal-references', [LegalReferenceController::class, 'index']);
    Route::put('/legal-references', [LegalReferenceController::class, 'update']);

    // Assinatura
    Route::get('/subscription/status', [SubscriptionController::class, 'status']);
    Route::post('/subscription/setup-intent', [SubscriptionController::class, 'setupIntent']);
    Route::post('/subscription/confirm', [SubscriptionController::class, 'confirm']);
    Route::post('/subscription/checkout-session', [SubscriptionController::class, 'checkoutSession']);
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']);
    Route::post('/subscription/resume', [SubscriptionController::class, 'resume']);
    Route::post('/subscription/validate-coupon', [SubscriptionController::class, 'validateCoupon']);

    // Perfil
    Route::patch('/profile', [AuthController::class, 'updateProfile'])->name('api.profile.update');
    Route::put('/profile/password', [AuthController::class, 'updatePassword'])->name('api.profile.password');
    Route::delete('/profile', [AuthController::class, 'deleteAccount'])->name('api.profile.delete');
});
