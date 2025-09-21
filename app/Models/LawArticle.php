<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LawArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'legal_reference_id',
        'original_content',
        'practice_content',
        'article_reference',
        'slug',
        'difficulty_level',
        'position',
        'uuid',
        'is_active'
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    /**
     * Generate a unique slug for this law article.
     */
    public function generateSlug(): string
    {
        $baseSlug = $this->createBaseSlug();
        $slug = $baseSlug;
        $counter = 1;

        while (static::where('slug', $slug)
            ->where('legal_reference_id', $this->legal_reference_id)
            ->where('id', '!=', $this->id ?? 0)
            ->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Create base slug from article reference and content.
     */
    private function createBaseSlug(): string
    {
        $article = 'art-' . $this->article_reference;

        $keywords = $this->extractKeywords($this->original_content);

        if (!empty($keywords)) {
            $article .= '-' . implode('-', $keywords);
        }

        return Str::slug($article);
    }

    /**
     * Extract relevant keywords from article content.
     */
    private function extractKeywords(string $content): array
    {
        $content = strtolower($content);

        $replacements = [
            'constituição' => 'constituicao',
            'vigência' => 'vigencia',
            'publicação' => 'publicacao',
            'revogação' => 'revogacao',
            'modificação' => 'modificacao',
            'disposição' => 'disposicao',
            'correção' => 'correcao',
            'aplicação' => 'aplicacao',
            'execução' => 'execucao',
            'prescrição' => 'prescricao',
            'homicídio' => 'homicidio',
            'educação' => 'educacao',
            'proteção' => 'protecao',
            'inviolabilidade' => 'inviolabilidade',
        ];

        foreach ($replacements as $from => $to) {
            $content = str_replace($from, $to, $content);
        }

        $importantTerms = [
            'direitos fundamentais' => 'direitos-fundamentais',
            'vida privada' => 'vida-privada',
            'liberdade' => 'liberdade',
            'igualdade' => 'igualdade',
            'homicidio' => 'homicidio',
            'vigencia' => 'vigencia',
            'publicacao' => 'publicacao',
            'revogacao' => 'revogacao',
            'modificacao' => 'modificacao',
            'inviolabilidade' => 'inviolabilidade',
            'seguranca' => 'seguranca',
            'propriedade' => 'propriedade',
            'domicilio' => 'domicilio',
            'correspondencia' => 'correspondencia',
            'manifestacao' => 'manifestacao',
            'reuniao' => 'reuniao',
            'associacao' => 'associacao',
            'profissao' => 'profissao',
            'locomocao' => 'locomocao',
            'contraditorio' => 'contraditorio',
            'ampla defesa' => 'ampla-defesa',
        ];

        $foundTerms = [];
        foreach ($importantTerms as $term => $slug) {
            if (str_contains($content, $term)) {
                $foundTerms[] = $slug;
            }
        }

        return array_slice($foundTerms, 0, 3);
    }

    /**
     * Obter a referência legal associada a este artigo.
     */
    public function legalReference(): BelongsTo
    {
        return $this->belongsTo(LegalReference::class);
    }

    /**
     * Obter as opções associadas a este artigo.
     */
    public function options(): HasMany
    {
        return $this->hasMany(LawArticleOption::class);
    }

    /**
     * O que acontece quando este modelo é excluído
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
            $model->slug = $model->slug ?? $model->generateSlug();
        });

        static::updating(function ($model) {
            if (($model->isDirty('article_reference') || $model->isDirty('original_content')) && !$model->isDirty('slug')) {
                $model->slug = $model->generateSlug();
            }
        });

        // Quando um artigo for excluído, exclua também todas as suas opções e progresso dos usuários
        static::deleting(function ($lawArticle) {
            // Deletar todas as opções do artigo
            $lawArticle->options()->delete();
            
            // Deletar todo o progresso dos usuários relacionado a este artigo
            // (já é tratado pelo onDelete('cascade') na migration, mas é bom garantir)
            DB::table('user_progress')
                ->where('law_article_id', $lawArticle->id)
                ->delete();
        });
    }
}
