<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LegalReference extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'uuid'
    ];

    /**
     * Obter os artigos associados a esta referência legal.
     */
    public function articles(): HasMany
    {
        return $this->hasMany(LawArticle::class);
    }

    /**
     * Obter os usuários associados a esta referência legal.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_legal_references')
                    ->withTimestamps();
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

        // Quando uma referência legal for excluída, exclua também todos os seus artigos
        static::deleting(function ($legalReference) {
            $legalReference->articles()->delete();
        });
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
