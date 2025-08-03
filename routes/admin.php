<?php

use App\Http\Controllers\Admin\LawArticleController;
use App\Http\Controllers\Admin\LegalReferenceController;
use App\Http\Controllers\Admin\LawArticleOptionController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::post('api/n8n/legal-references', [LegalReferenceController::class, 'storeFromN8n']);


Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    // Rotas para referências legais
    Route::get('legal-references', [LegalReferenceController::class, 'index'])->name('admin.legal-references.index');
    Route::post('legal-references', [LegalReferenceController::class, 'store'])->name('admin.legal-references.store');
    Route::get('legal-references/{legalReference:uuid}', [LegalReferenceController::class, 'show'])->name('admin.legal-references.show');
    Route::put('legal-references/{legalReference:uuid}', [LegalReferenceController::class, 'update'])->name('admin.legal-references.update');
    Route::delete('legal-references/{legalReference:uuid}', [LegalReferenceController::class, 'destroy'])->name('admin.legal-references.destroy');

    // Rotas para artigos de lei
    Route::get('create-lawarticle', [LawArticleController::class, 'create'])->name('admin.form.create');
    Route::post('form', [LawArticleController::class, 'store'])->name('admin.form.store');

    // Novas rotas para edição de artigos
    Route::get('edit-lawarticle/{lawArticle:uuid}', [LawArticleController::class, 'edit'])->name('admin.form.edit');
    Route::put('edit-lawarticle/{lawArticle:uuid}', [LawArticleController::class, 'update'])->name('admin.form.update');

    // Rotas para opções de artigos
    Route::post('law-article-options', [LawArticleOptionController::class, 'store'])->name('admin.law-article-options.store');

    // Rota para remover uma opção específica
    Route::delete('law-article-options/{option:uuid}', [LawArticleOptionController::class, 'destroy'])
        ->name('admin.law-article-options.destroy');

    // Novas rotas para visualização de legislações
    Route::get('legislations', [LegalReferenceController::class, 'legislationsIndex'])->name('admin.legislations.index');
    Route::get('legislations/{legalReference:uuid}', [LegalReferenceController::class, 'legislationsShow'])->name('admin.legislations.show');

    // Rota para visualização de usuários
    Route::get('users', [UserController::class, 'index'])->name('admin.users.index');
});
