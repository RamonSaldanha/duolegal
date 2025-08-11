<?php

namespace App\Http\Controllers;

use App\Models\LegalReference;
use App\Models\LawArticle;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicController extends Controller
{
    /**
     * Página inicial com todas as leis disponíveis (SEO)
     */
    public function index()
    {
        $legalReferences = LegalReference::where('is_active', true)
            ->with(['articles' => function($query) {
                $query->where('is_active', true)
                      ->orderByRaw('CAST(article_reference AS UNSIGNED) ASC')
                      ->take(5); // Mostrar apenas os primeiros 5 artigos
            }])
            ->orderBy('name')
            ->get()
            ->map(function($reference) {
                return [
                    'uuid' => $reference->uuid,
                    'name' => $this->sanitizeString($reference->name),
                    'description' => $this->sanitizeString($reference->description),
                    'type' => $this->sanitizeString($reference->type),
                    'total_articles' => $reference->articles()->where('is_active', true)->count(),
                    'sample_articles' => $reference->articles->map(function($article) {
                        return [
                            'uuid' => $article->uuid,
                            'article_reference' => $this->sanitizeString($article->article_reference),
                            'difficulty_level' => $article->difficulty_level,
                        ];
                    })
                ];
            });

        return Inertia::render('Public/Laws', [
            'legalReferences' => $legalReferences,
            'meta' => [
                'title' => 'Todas as Leis - Memorize Direito | Estude Direito de Forma Gamificada',
                'description' => 'Explore todas as leis disponíveis no Memorize Direito. Pratique artigos de leis como Código Civil, Código Penal, Constituição Federal e muito mais.',
                'keywords' => 'direito, leis, código civil, código penal, constituição federal, estudo direito, oab, concurso público'
            ]
        ]);
    }

    /**
     * Página de uma lei específica com todos os seus artigos (SEO)
     */
    public function showLaw($uuid)
    {
        $legalReference = LegalReference::where('uuid', $uuid)
            ->where('is_active', true)
            ->with(['articles' => function($query) {
                $query->where('is_active', true)
                      ->orderByRaw('CAST(article_reference AS UNSIGNED) ASC');
            }])
            ->firstOrFail();

        $articles = $legalReference->articles->map(function($article) use ($legalReference) {
            return [
                'uuid' => $article->uuid,
                'article_reference' => $this->sanitizeString($article->article_reference),
                'difficulty_level' => $article->difficulty_level,
                'preview_content' => $this->sanitizeString(substr(strip_tags($article->original_content), 0, 150) . '...'),
                'law_name' => $this->sanitizeString($legalReference->name),
                'has_exercise' => !empty($article->practice_content),
            ];
        });

        return Inertia::render('Public/LawDetail', [
            'law' => [
                'uuid' => $legalReference->uuid,
                'name' => $this->sanitizeString($legalReference->name),
                'description' => $this->sanitizeString($legalReference->description),
                'type' => $this->sanitizeString($legalReference->type),
            ],
            'articles' => $articles,
            'meta' => [
                'title' => $this->sanitizeString($legalReference->name) . ' - Memorize Direito | Pratique Todos os Artigos',
                'description' => $this->sanitizeString("Pratique todos os artigos do(a) {$legalReference->name}. Exercícios interativos para memorizar e dominar cada artigo da lei."),
                'keywords' => $this->sanitizeString(strtolower($legalReference->name) . ', artigos, exercícios, direito, estudo, memorização')
            ]
        ]);
    }

    /**
     * Página de um artigo específico com exercício público (SEO)
     */
    public function showArticle($lawUuid, $articleUuid)
    {
        $article = LawArticle::where('uuid', $articleUuid)
            ->where('is_active', true)
            ->with(['legalReference', 'options'])
            ->firstOrFail();

        // Verificar se o artigo pertence à lei especificada
        if ($article->legalReference->uuid !== $lawUuid) {
            abort(404);
        }

        // Preparar dados do artigo para o exercício público
        $articleData = [
            'uuid' => $article->uuid,
            'article_reference' => $this->sanitizeString($article->article_reference),
            'original_content' => $this->sanitizeString($article->original_content),
            'practice_content' => $this->sanitizeString($article->practice_content),
            'difficulty_level' => $article->difficulty_level,
            'law_name' => $this->sanitizeString($article->legalReference->name),
            'law_uuid' => $article->legalReference->uuid,
            'options' => $article->options->map(function($option) {
                return [
                    'id' => $option->id,
                    'word' => $this->sanitizeString($option->word),
                    'is_correct' => $option->is_correct,
                    'gap_order' => $option->gap_order,
                    'position' => $option->position
                ];
            })->sortBy('position')->values()->all(),
        ];

        // Buscar artigos relacionados (sempre artigos posteriores para melhor SEO)
        $currentArticleNumber = (int) preg_replace('/[^0-9]/', '', $article->article_reference);
        
        $relatedArticles = LawArticle::where('legal_reference_id', $article->legal_reference_id)
            ->where('is_active', true)
            ->where('uuid', '!=', $articleUuid)
            ->whereRaw('CAST(REGEXP_REPLACE(article_reference, "[^0-9]", "") AS UNSIGNED) > ?', [$currentArticleNumber])
            ->orderByRaw('CAST(article_reference AS UNSIGNED) ASC')
            ->take(5)
            ->get()
            ->map(function($relatedArticle) use ($lawUuid) {
                return [
                    'uuid' => $relatedArticle->uuid,
                    'article_reference' => $this->sanitizeString($relatedArticle->article_reference),
                    'preview_content' => $this->sanitizeString(substr(strip_tags($relatedArticle->original_content), 0, 100) . '...'),
                    'url' => route('public.article', ['lawUuid' => $lawUuid, 'articleUuid' => $relatedArticle->uuid])
                ];
            });

        $difficultyText = $this->getDifficultyText($article->difficulty_level);

        // Criar uma meta description mais rica com trechos do conteúdo original
        $originalContentPreview = $this->sanitizeString(substr(strip_tags($article->original_content), 0, 120));
        $metaDescription = $this->sanitizeString("Art. {$article->article_reference} - {$article->legalReference->name}: \"{$originalContentPreview}...\" Pratique este artigo com exercício interativo gratuito. Nível: {$difficultyText}.");
        
        return Inertia::render('Public/ArticleExercise', [
            'article' => $articleData,
            'relatedArticles' => $relatedArticles,
            'meta' => [
                'title' => $this->sanitizeString("Art. {$article->article_reference} - {$article->legalReference->name} | Memorize Direito"),
                'description' => $metaDescription,
                'keywords' => $this->sanitizeString("artigo {$article->article_reference}, {$article->legalReference->name}, direito, exercício, {$difficultyText}, memorização, estudo")
            ]
        ]);
    }

    /**
     * Página de busca de artigos (SEO)
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        $results = collect();

        if (strlen($query) >= 2) {
            $results = LawArticle::where('is_active', true)
                ->with('legalReference')
                ->where(function($q) use ($query) {
                    $q->where('article_reference', 'like', '%' . $query . '%')
                      ->orWhere('original_content', 'like', '%' . $query . '%');
                })
                ->orderByRaw('CAST(article_reference AS UNSIGNED) ASC')
                ->take(20)
                ->get()
                ->map(function($article) {
                    return [
                        'uuid' => $article->uuid,
                        'article_reference' => $this->sanitizeString($article->article_reference),
                        'law_name' => $this->sanitizeString($article->legalReference->name),
                        'law_uuid' => $article->legalReference->uuid,
                        'preview_content' => $this->sanitizeString(substr(strip_tags($article->original_content), 0, 200) . '...'),
                        'difficulty_level' => $article->difficulty_level,
                        'url' => route('public.article', [
                            'lawUuid' => $article->legalReference->uuid,
                            'articleUuid' => $article->uuid
                        ])
                    ];
                });
        }

        return Inertia::render('Public/Search', [
            'query' => $query,
            'results' => $results,
            'meta' => [
                'title' => $query ? "Busca por '{$query}' - Memorize Direito" : 'Buscar Artigos - Memorize Direito',
                'description' => $query 
                    ? "Resultados da busca por '{$query}' nos artigos de direito. Encontre e pratique artigos específicos."
                    : 'Busque por artigos específicos de leis. Encontre rapidamente o conteúdo que você precisa estudar.',
                'keywords' => $query ? "busca, {$query}, artigos, direito" : 'busca, artigos, direito, pesquisa'
            ]
        ]);
    }

    /**
     * Retorna o texto referente ao nível de dificuldade
     */
    private function getDifficultyText(int $level): string
    {
        return match($level) {
            1 => 'Iniciante',
            2 => 'Básico',
            3 => 'Intermediário',
            4 => 'Avançado',
            5 => 'Especialista',
            default => 'Intermediário'
        };
    }

    /**
     * Sanitiza strings para evitar problemas de encoding UTF-8
     */
    private function sanitizeString(?string $string): string
    {
        if (empty($string)) {
            return '';
        }

        // Remove caracteres de controle invisíveis e malformados
        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $string);
        
        // Corrige encoding UTF-8
        if (!mb_check_encoding($string, 'UTF-8')) {
            $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        }
        
        // Remove sequências UTF-8 inválidas
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        
        // Garante que seja uma string válida
        return trim($string) ?: '';
    }
}