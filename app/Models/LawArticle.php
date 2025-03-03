<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LawArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'legal_reference_id',
        'original_content',
        'practice_content',
        'article_reference',
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
        return 'uuid';
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
        });

        // Quando um artigo for excluído, exclua também todas as suas opções
        static::deleting(function ($lawArticle) {
            $lawArticle->options()->delete();
        });
    }
}
