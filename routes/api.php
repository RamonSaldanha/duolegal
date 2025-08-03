<?php

use App\Http\Controllers\Api\LawArticleApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\LegalReferenceController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('n8n/legal-references', [LegalReferenceController::class, 'storeFromN8n']);


// Rotas da API para artigos de legislação
Route::prefix('law-articles')->group(function () {
    // Endpoint para cadastrar um novo artigo
    Route::post('/', [LawArticleApiController::class, 'store'])->name('api.law-articles.store');
    
    // Endpoint para obter referências legais disponíveis
    Route::get('/legal-references', [LawArticleApiController::class, 'getLegalReferences'])->name('api.law-articles.legal-references');
});
