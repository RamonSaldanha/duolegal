<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LawArticle;
use App\Models\LawArticleOption;
use App\Models\LegalReference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LawArticleApiController extends Controller
{
    /**
     * Cadastrar um novo artigo de legislação via API
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'legal_reference_id' => 'required|exists:legal_references,id',
                'original_content' => 'required|string|max:5000',
                'practice_content' => 'required|string|max:5000',
                'article_reference' => 'nullable|string|max:255',
                'difficulty_level' => 'nullable|integer|min:1|max:5',
                'position' => 'nullable|integer|min:1',
                'is_active' => 'nullable|boolean',
                'selected_words' => 'array',
                'selected_words.*.word' => 'required|string|max:255',
                'selected_words.*.position' => 'required|integer|min:0',
                'selected_words.*.gap_order' => 'nullable|integer|min:1',
                'custom_options' => 'array',
                'custom_options.*.word' => 'required|string|max:255',
            ]);

            // Define a posição se não for fornecida
            if (!isset($validated['position'])) {
                $maxPosition = LawArticle::where('legal_reference_id', $validated['legal_reference_id'])
                    ->max('position');
                $validated['position'] = ($maxPosition ?? 0) + 10;
            }

            $article = DB::transaction(function () use ($validated) {
                // Cria o artigo
                $article = LawArticle::create([
                    'legal_reference_id' => $validated['legal_reference_id'],
                    'original_content' => $validated['original_content'],
                    'practice_content' => $validated['practice_content'],
                    'article_reference' => $validated['article_reference'],
                    'difficulty_level' => $validated['difficulty_level'] ?? 1,
                    'position' => $validated['position'],
                    'is_active' => $validated['is_active'] ?? true,
                ]);

                // Cria as opções a partir das palavras selecionadas (opções corretas)
                if (!empty($validated['selected_words'])) {
                    foreach ($validated['selected_words'] as $index => $word) {
                        LawArticleOption::create([
                            'law_article_id' => $article->id,
                            'word' => $word['word'],
                            'is_correct' => true,
                            'position' => $word['position'],
                            'gap_order' => $word['gap_order'] ?? ($index + 1)
                        ]);
                    }
                }

                // Cria as opções personalizadas (opções incorretas/distratoras)
                if (!empty($validated['custom_options'])) {
                    foreach ($validated['custom_options'] as $option) {
                        LawArticleOption::create([
                            'law_article_id' => $article->id,
                            'word' => $option['word'],
                            'is_correct' => false,
                            'position' => null,
                            'gap_order' => null
                        ]);
                    }
                }

                return $article;
            });

            // Carrega o artigo com suas opções e referência legal para retornar
            $article->load(['options', 'legalReference']);

            return response()->json([
                'success' => true,
                'message' => 'Artigo de legislação cadastrado com sucesso.',
                'data' => [
                    'article' => [
                        'uuid' => $article->uuid,
                        'legal_reference_id' => $article->legal_reference_id,
                        'original_content' => $article->original_content,
                        'practice_content' => $article->practice_content,
                        'article_reference' => $article->article_reference,
                        'difficulty_level' => $article->difficulty_level,
                        'position' => $article->position,
                        'is_active' => $article->is_active,
                        'legal_reference' => [
                            'uuid' => $article->legalReference->uuid,
                            'name' => $article->legalReference->name,
                            'type' => $article->legalReference->type,
                        ],
                        'options' => $article->options->map(function ($option) {
                            return [
                                'uuid' => $option->uuid,
                                'word' => $option->word,
                                'is_correct' => $option->is_correct,
                                'position' => $option->position,
                                'gap_order' => $option->gap_order,
                            ];
                        }),
                        'created_at' => $article->created_at,
                        'updated_at' => $article->updated_at,
                    ]
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos fornecidos.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor ao cadastrar o artigo.',
                'error' => config('app.debug') ? $e->getMessage() : 'Erro interno do servidor.'
            ], 500);
        }
    }

    /**
     * Listar todas as referências legais disponíveis
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLegalReferences()
    {
        try {
            $legalReferences = LegalReference::select('id', 'uuid', 'name', 'description', 'type', 'is_active')
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'legal_references' => $legalReferences
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar referências legais.',
                'error' => config('app.debug') ? $e->getMessage() : 'Erro interno do servidor.'
            ], 500);
        }
    }
}
