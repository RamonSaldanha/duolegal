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
     * Criar referência legal via n8n (sem auth)
     */
    public function storeFromN8n(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
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
            'type' => $request->type ?? 'law',
        ]);

        return response()->json([
            'id' => $reference->id,
            'name' => $reference->name,
            'created_at' => $reference->created_at,
            'status' => 'success'
        ], 201);
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
        try {
            // A exclusão em cascata é tratada pelas foreign keys e pelos boot methods dos models
            // Isso irá deletar automaticamente:
            // - Todos os artigos da legislação (através do boot method do LegalReference)
            // - Todas as opções dos artigos (através do boot method do LawArticle)
            // - Todo o progresso dos usuários (através das foreign keys cascade)
            // - Todas as associações user_legal_references (através das foreign keys cascade)
            
            $legalReference->delete();
            
            return response()->json([
                'message' => 'Legislação excluída com sucesso.'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao excluir a legislação. Tente novamente.'
            ], 500);
        }
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
        // Carregar os artigos ordenados por referência numérica e incluir as opções
        $legalReference->load(['articles' => function($query) {
            $query->orderByRaw('CAST(article_reference AS UNSIGNED) ASC');
        }, 'articles.options']);
        
        return Inertia::render('admin/Legislations/Show', [
            'legalReference' => $legalReference
        ]);
    }
}
