<?php

namespace App\Http\Controllers;

use App\Models\LegalReference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UserLegalReferenceController extends Controller
{
    /**
     * Mostrar página de seleção de legislações
     */
    public function index()
    {
        // Busca todas as leis disponíveis, ordenadas por nome
        $legalReferences = LegalReference::orderBy('name')->get();

        // Busca as legislações já selecionadas pelo usuário
        $userReferences = Auth::user()->legalReferences->pluck('id')->toArray();

        return Inertia::render('User/LegalReferences', [
            'legalReferences' => $legalReferences,
            'userReferences' => $userReferences,
        ]);
    }

    /**
     * Salvar as preferências do usuário
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'references' => 'required|array',
            'references.*' => 'exists:legal_references,id',
        ]);

        // Sincroniza as preferências do usuário
        Auth::user()->legalReferences()->sync($validated['references']);

        return redirect()->back()->with('message', 'Suas preferências de legislações foram salvas com sucesso!');
    }
}
