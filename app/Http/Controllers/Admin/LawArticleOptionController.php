<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LawArticleOption;
use Illuminate\Http\Request;

class LawArticleOptionController extends Controller
{
    /**
     * Listar todas as opções de artigos de lei
     */
    public function index()
    {
        $options = LawArticleOption::select('id', 'uuid', 'word')
            ->get();
            
        return response()->json($options);
    }
    
    /**
     * Armazenar uma nova opção (usada pelo SearchCreateSelect)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'word' => 'required|string|max:255',
            'gap_order' => 'nullable|integer', // Adicionamos validação para gap_order
        ]);
        
        // Não precisamos salvar no banco aqui, apenas retornar
        // para ser usado pelo componente, a opção será salvada
        // junto com o artigo depois
        
        return response()->json([
            'id' => null,  // ID temporário
            'uuid' => null,
            'word' => $validated['word'],
            'gap_order' => $request->gap_order ?? null, // Incluímos gap_order na resposta
        ]);
    }

    /**
     * Remover uma opção específica
     */
    public function destroy(LawArticleOption $option)
    {
        try {
            $option->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
