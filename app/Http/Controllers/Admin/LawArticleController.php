<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LawArticle;
use App\Models\LawArticleOption;
use App\Models\LegalReference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LawArticleController extends Controller
{
    /**
     * Mostrar o formulário de criação de artigo.
     */
    public function create()
    {
        $legalReferences = LegalReference::all();
        
        return Inertia::render('admin/CreateLawArticle', [
            'legalReferences' => $legalReferences
        ]);
    }

    /**
     * Armazenar um novo artigo.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'legal_reference_id' => 'required|exists:legal_references,id',
            'original_content' => 'required|string',
            'practice_content' => 'required|string', // Adicionando validação para practice_content
            'article_reference' => 'nullable|string',
            'difficulty_level' => 'nullable|integer|min:1|max:5',
            'position' => 'nullable|integer',
            'selected_words' => 'array',
            'selected_words.*.word' => 'string',
            'selected_words.*.position' => 'integer',
            'selected_words.*.gap_order' => 'nullable|integer', // Adicionando validação para gap_order
            'custom_options' => 'array',
            'custom_options.*.word' => 'string',
        ]);

        // Define a posição se não for fornecida
        if (!isset($validated['position'])) {
            $maxPosition = LawArticle::where('legal_reference_id', $validated['legal_reference_id'])
                ->max('position');
            $validated['position'] = ($maxPosition ?? 0) + 10;
        }

        try {
            DB::transaction(function () use ($validated, &$article) {
                // Cria o artigo
                $article = LawArticle::create([
                    'legal_reference_id' => $validated['legal_reference_id'],
                    'original_content' => $validated['original_content'],
                    'practice_content' => $validated['practice_content'], // Usa o valor do request
                    'article_reference' => $validated['article_reference'],
                    'difficulty_level' => $validated['difficulty_level'] ?? 1,
                    'position' => $validated['position'],
                ]);

                // Cria as opções a partir das palavras selecionadas
                if (!empty($validated['selected_words'])) {
                    foreach ($validated['selected_words'] as $index => $word) {
                        LawArticleOption::create([
                            'law_article_id' => $article->id,
                            'word' => $word['word'],
                            'is_correct' => true,
                            'position' => $word['position'],
                            'gap_order' => $word['gap_order'] ?? ($index + 1) // Usa gap_order se disponível, ou a ordem de seleção
                        ]);
                    }
                }

                // Cria as opções personalizadas
                if (!empty($validated['custom_options'])) {
                    foreach ($validated['custom_options'] as $option) {
                        LawArticleOption::create([
                            'law_article_id' => $article->id,
                            'word' => $option['word'],
                            'is_correct' => false,
                            'position' => null,
                            'gap_order' => null // Opções personalizadas não são parte do texto original
                        ]);
                    }
                }
            });
        } catch (\Exception $e) {
            // Em caso de erro, redireciona com mensagem de erro
            return redirect()->back()->withErrors(['message' => 'Erro ao salvar o artigo: ' . $e->getMessage()]);
        }

        // Redireciona com mensagem de sucesso
        return redirect()->back()
            ->with('success', 'Artigo de lei adicionado com sucesso.');
    }

    /**
     * Mostrar o formulário de edição de artigo.
     */
    public function edit(LawArticle $lawArticle)
    {
        // Carregando artigo com opções e referência legal
        $lawArticle->load(['options', 'legalReference']);
        
        // Carregar todas as referências legais para o dropdown
        $legalReferences = LegalReference::all();
        
        // Selecionar palavras para exibir como lacunas
        // Recuperar os índices das palavras que foram transformadas em lacunas
        $selectedWordIndices = [];
        
        // Para cada opção que pertence ao texto original (is_correct=true)
        foreach ($lawArticle->options as $option) {
            if ($option->is_correct && $option->position !== null) {
                $selectedWordIndices[] = $option->position;
            }
        }
        
        // Opções personalizadas (is_correct=false)
        $customOptions = $lawArticle->options
                        ->filter(function ($option) {
                            return !$option->is_correct;
                        })
                        ->values();
        
        return Inertia::render('admin/EditLawArticle', [
            'lawArticle' => $lawArticle,
            'legalReferences' => $legalReferences,
            'selectedWordIndices' => $selectedWordIndices,
            'customOptions' => $customOptions,
        ]);
    }

    /**
     * Atualizar um artigo existente.
     */
    public function update(Request $request, LawArticle $lawArticle)
    {
        $validated = $request->validate([
            'legal_reference_id' => 'required|exists:legal_references,id',
            'original_content' => 'required|string',
            'practice_content' => 'required|string',
            'article_reference' => 'nullable|string',
            'difficulty_level' => 'nullable|integer|min:1|max:5',
            'position' => 'nullable|integer',
            'selected_words' => 'array',
            'selected_words.*.word' => 'string',
            'selected_words.*.position' => 'integer',
            'selected_words.*.gap_order' => 'nullable|integer', // Adicionando validação para gap_order
            'custom_options' => 'array',
            'custom_options.*.word' => 'string',
        ]);
        
        try {
            DB::transaction(function () use ($validated, $lawArticle) {
                // Atualizar o artigo
                $lawArticle->update([
                    'legal_reference_id' => $validated['legal_reference_id'],
                    'original_content' => $validated['original_content'],
                    'practice_content' => $validated['practice_content'],
                    'article_reference' => $validated['article_reference'],
                    'difficulty_level' => $validated['difficulty_level'] ?? 1,
                    'position' => $validated['position'],
                ]);
                
                // Excluir todas as opções antigas
                $lawArticle->options()->delete();
                
                // Adicionar as palavras selecionadas como opções corretas
                foreach ($validated['selected_words'] as $index => $word) {
                    LawArticleOption::create([
                        'law_article_id' => $lawArticle->id,
                        'word' => $word['word'],
                        'is_correct' => true,
                        'position' => $word['position'],
                        'gap_order' => $word['gap_order'] ?? ($index + 1) // Usa gap_order se disponível, ou a ordem de seleção
                    ]);
                }
                
                // Adicionar opções personalizadas
                foreach ($validated['custom_options'] as $option) {
                    LawArticleOption::create([
                        'law_article_id' => $lawArticle->id,
                        'word' => $option['word'],
                        'is_correct' => false,
                        'position' => null,
                        'gap_order' => null // Opções personalizadas não são parte do texto original
                    ]);
                }
            });
            
            return redirect()->route('admin.legislations.show', $lawArticle->legalReference->uuid)
                ->with('success', 'Artigo atualizado com sucesso.');
        } catch (\Exception $e) {
            return back()->withErrors(['message' => 'Erro ao atualizar o artigo: ' . $e->getMessage()]);
        }
    }

    // Outros métodos (edit, update, destroy, etc.)
}
