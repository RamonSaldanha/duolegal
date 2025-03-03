<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LawArticleOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'law_article_id',
        'word',
        'is_correct',
        'position',
        'uuid',
        'gap_order',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName()
    {
        return 'uuid';
    }

    /**
     * Boot function from Laravel.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
        });
    }

    /**
     * Obter o artigo associado a esta opção.
     */
    public function lawArticle(): BelongsTo
    {
        return $this->belongsTo(LawArticle::class);
    }
}
