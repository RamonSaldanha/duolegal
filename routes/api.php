<?php

use App\Http\Controllers\Admin\LegalReferenceController as AdminLegalReferenceController;
use App\Http\Controllers\Api\LawArticleApiController;
use App\Http\Controllers\Api\V1\Admin\StudyMaterialController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DisciplineProgressController;
use App\Http\Controllers\Api\V1\LegalReferenceController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\PlayController;
use App\Http\Controllers\Api\V1\PublicProfileController;
use App\Http\Controllers\Api\V1\RankingController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\StreakController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TrailChallengeController;
use App\Http\Controllers\Api\V1\TrailController;
use App\Http\Controllers\Api\V1\TrailExamController;
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

    // Onboarding (só de quem se cadastrou pelo app novo; o site tem o seu)
    Route::get('/onboarding', [OnboardingController::class, 'show']);
    Route::post('/onboarding/trail', [OnboardingController::class, 'saveTrail']);
    Route::post('/onboarding/challenge', [OnboardingController::class, 'completeChallenge']);
    Route::post('/onboarding/offer', [OnboardingController::class, 'startOffer']);
    Route::post('/onboarding/finish', [OnboardingController::class, 'finish']);

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

    // Mapa novo (quadrados de 4 fases): o mapa do app novo, para todo aluno. Os dados são os
    // de /beta/play na web (que segue só para administradores). O app antigo usa /play.
    Route::prefix('trail')->group(function () {
        Route::get('/', [TrailController::class, 'index']);
        Route::get('/phases/{phaseId}', [TrailController::class, 'phase'])->where('phaseId', '[0-9]+');
        Route::post('/attempt', [TrailController::class, 'betaAttempt']);
        // Só dos builds anteriores à energia
        Route::post('/fail', [TrailController::class, 'betaFail']);
        Route::post('/finish', [TrailController::class, 'betaFinish']);
        Route::get('/energy', [TrailController::class, 'energy']);
        Route::post('/energy/reward', [TrailController::class, 'betaEnergyReward'])->middleware('throttle:12,60');

        Route::get('/challenges/{checkpoint}', [TrailChallengeController::class, 'challenge'])->whereUuid('checkpoint');
        Route::post('/challenges/{checkpoint}/summary', [TrailChallengeController::class, 'summary'])->whereUuid('checkpoint');
        Route::post('/challenges/{checkpoint}/answer', [TrailChallengeController::class, 'answer'])->whereUuid('checkpoint');

        Route::get('/exams/{exam}', [TrailExamController::class, 'exam'])->whereUuid('exam');
        Route::post('/exams/{exam}/start', [TrailExamController::class, 'start'])->whereUuid('exam');
        Route::post('/exams/{exam}/answer', [TrailExamController::class, 'answer'])->whereUuid('exam');
        Route::post('/exams/{exam}/finish', [TrailExamController::class, 'finish'])->whereUuid('exam');
    });

    // Ranking
    Route::get('/ranking', [RankingController::class, 'index']);

    // Perfil público de quem está no ranking (pelo uuid, para ninguém listar os usuários por id)
    Route::get('/profiles/{user:uuid}', [PublicProfileController::class, 'profile']);

    // Configurações do perfil: descrição, conquistas no perfil público, e-mails de lembrete
    Route::get('/settings', [SettingsController::class, 'show']);
    Route::put('/settings/achievements', [SettingsController::class, 'updateAchievements']);
    Route::put('/settings/emails', [SettingsController::class, 'updateEmails']);

    // Ofensiva (streak de dias consecutivos)
    Route::get('/streak', [StreakController::class, 'show']);

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
    Route::post('/subscription/sync', [SubscriptionController::class, 'syncFromCheckout']);
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']);
    Route::post('/subscription/resume', [SubscriptionController::class, 'resume']);
    Route::post('/subscription/validate-coupon', [SubscriptionController::class, 'validateCoupon']);

    // Perfil
    Route::patch('/profile', [AuthController::class, 'updateProfile'])->name('api.profile.update');
    Route::put('/profile/password', [AuthController::class, 'updatePassword'])->name('api.profile.password');
    Route::put('/profile/avatar', [AuthController::class, 'updateAvatar'])->name('api.profile.avatar');
    Route::delete('/profile', [AuthController::class, 'deleteAccount'])->name('api.profile.delete');
});

// Material de estudo: rotas do agente de IA (ver .claude/skills/gerar-material-estudo).
// Exige admin e token com a habilidade `study-materials`; o token do app não serve.
Route::prefix('v1/admin/study-materials')
    ->middleware(['auth:sanctum', 'throttle:120,1', 'study-materials.agent'])
    ->group(function () {
        Route::get('/legislations', [StudyMaterialController::class, 'legislations']);
        Route::get('/next', [StudyMaterialController::class, 'next']);
        Route::get('/status', [StudyMaterialController::class, 'status']);
        Route::post('/', [StudyMaterialController::class, 'store']);
        Route::post('/skip', [StudyMaterialController::class, 'skip']);
    });
