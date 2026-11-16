<?php

use App\Http\Controllers\Admin\DisciplineController;
use App\Http\Controllers\Admin\LawArticleController;
use App\Http\Controllers\Admin\LawArticleOptionController;
use App\Http\Controllers\Admin\LegalReferenceController;
use App\Http\Controllers\Admin\StudyMaterialController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

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

    // Rota para exportação de contatos para Brevo
    Route::get('users/export-brevo', [UserController::class, 'exportBrevoContacts'])->name('admin.users.export-brevo');

    // Ficha completa de um usuário
    Route::get('users/{user}', [UserController::class, 'show'])->whereNumber('user')->name('admin.users.show');

    // Prévia do lembrete por e-mail (?gift=xp_boost, ?sequence=2)
    Route::get('emails/lembrete', [UserController::class, 'previewEmail'])->name('admin.emails.preview');

    // Rota para adicionar vidas a um usuário
    Route::post('users/{user}/add-lives', [UserController::class, 'addLives'])->name('admin.users.add-lives');

    // Rota para apagar a descrição do perfil de um usuário
    Route::delete('users/{user}/bio', [UserController::class, 'clearBio'])->name('admin.users.clear-bio');

    // Rotas para disciplinas
    Route::get('disciplines', [DisciplineController::class, 'index'])->name('admin.disciplines.index');
    Route::get('disciplines/create', [DisciplineController::class, 'create'])->name('admin.disciplines.create');
    Route::post('disciplines', [DisciplineController::class, 'store'])->name('admin.disciplines.store');
    Route::get('disciplines/{discipline}/edit', [DisciplineController::class, 'edit'])->name('admin.disciplines.edit');
    Route::put('disciplines/{discipline}', [DisciplineController::class, 'update'])->name('admin.disciplines.update');
    Route::delete('disciplines/{discipline}', [DisciplineController::class, 'destroy'])->name('admin.disciplines.destroy');

    // Material de estudo (resumos + questões gravados por agentes de IA)
    Route::get('study-materials', [StudyMaterialController::class, 'index'])->name('admin.study-materials.index');
    Route::post('study-materials/preview', [StudyMaterialController::class, 'preview'])->name('admin.study-materials.preview');

    // Página "Instalar a skill": token do agente e download da skill
    Route::get('study-materials/instalar', [StudyMaterialController::class, 'install'])->name('admin.study-materials.install');
    Route::post('study-materials/tokens', [StudyMaterialController::class, 'createToken'])->name('admin.study-materials.tokens.store');
    Route::delete('study-materials/tokens/{token}', [StudyMaterialController::class, 'revokeToken'])->whereNumber('token')->name('admin.study-materials.tokens.destroy');
    // Sem ".zip" no endereço: o nginx de produção trata essa extensão como arquivo estático e devolve 404
    Route::get('study-materials/baixar-skill', [StudyMaterialController::class, 'downloadSkill'])->name('admin.study-materials.skill');

    Route::get('study-materials/{studyMaterial}', [StudyMaterialController::class, 'edit'])->whereUuid('studyMaterial')->name('admin.study-materials.edit');
    Route::put('study-materials/{studyMaterial}', [StudyMaterialController::class, 'update'])->whereUuid('studyMaterial')->name('admin.study-materials.update');
    Route::post('study-materials/{studyMaterial}/publish', [StudyMaterialController::class, 'publish'])->whereUuid('studyMaterial')->name('admin.study-materials.publish');
    Route::post('study-materials/{studyMaterial}/unpublish', [StudyMaterialController::class, 'unpublish'])->whereUuid('studyMaterial')->name('admin.study-materials.unpublish');
    Route::post('study-materials/{studyMaterial}/confirm', [StudyMaterialController::class, 'confirm'])->whereUuid('studyMaterial')->name('admin.study-materials.confirm');
    Route::delete('study-materials/{studyMaterial}', [StudyMaterialController::class, 'destroy'])->whereUuid('studyMaterial')->name('admin.study-materials.destroy');
});
