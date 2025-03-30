<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\LegalReference;
use Illuminate\Support\Facades\Auth;

class UserLegalReferenceController extends Controller
{
    /**
     * Mostrar página de seleção de legislações
     */
    public function index()
    {
        // Busca todas as leis disponíveis
        $legalReferences = LegalReference::all();
        
        // Busca as legislações já selecionadas pelo usuário
        $userReferences = Auth::user()->legalReferences->pluck('id')->toArray();
        
        return Inertia::render('User/LegalReferences', [
            'legalReferences' => $legalReferences,
            'userReferences' => $userReferences
        ]);
    }
    
    /**
     * Salvar as preferências do usuário
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'references' => 'required|array',
            'references.*' => 'exists:legal_references,id'
        ]);
        
        // Sincroniza as preferências do usuário
        Auth::user()->legalReferences()->sync($validated['references']);
        
        return redirect()->back()->with('message', 'Suas preferências de legislações foram salvas com sucesso!');
    }
}
