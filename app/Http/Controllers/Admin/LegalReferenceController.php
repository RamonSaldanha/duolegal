<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalReference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LegalReferenceController extends Controller
{
    /**
     * Listar todas as referências legais.
     */
    public function index()
    {
        $references = LegalReference::orderBy('name')->get();
        
        return response()->json($references);
    }

    /**
     * Armazenar uma nova referência legal.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:legal_references'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $reference = LegalReference::create([
            'name' => $request->name,
            'description' => $request->description,
            'type' => $request->type ?? 'law',
        ]);

        return response()->json($reference, 201);
    }

    /**
     * Mostrar uma referência legal específica.
     */
    public function show(LegalReference $legalReference)
    {
        return response()->json($legalReference);
    }

    /**
     * Atualizar uma referência legal existente.
     */
    public function update(Request $request, LegalReference $legalReference)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', Rule::unique('legal_references')->ignore($legalReference->id)],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $legalReference->update($request->all());

        return response()->json($legalReference);
    }

    /**
     * Remover uma referência legal.
     */
    public function destroy(LegalReference $legalReference)
    {
        // Verificar se há artigos vinculados
        if ($legalReference->articles()->count() > 0) {
            return response()->json([
                'message' => 'Esta referência possui artigos vinculados e não pode ser excluída.'
            ], 422);
        }
        
        $legalReference->delete();
        
        return response()->json(null, 204);
    }

    /**
     * Exibe a página de visualização de todas as legislações
     */
    public function legislationsIndex()
    {
        $legalReferences = LegalReference::withCount('articles')->get();
        
        return Inertia::render('admin/Legislations/Index', [
            'legalReferences' => $legalReferences
        ]);
    }

    /**
     * Exibe a página de visualização detalhada de uma legislação
     */
    public function legislationsShow(LegalReference $legalReference)
    {
        // Carregar os artigos ordenados por posição e incluir as opções
        $legalReference->load(['articles' => function($query) {
            $query->orderBy('position', 'asc');
        }, 'articles.options']);
        
        return Inertia::render('admin/Legislations/Show', [
            'legalReference' => $legalReference
        ]);
    }
}
