<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LegalReference extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'uuid',
        'is_active'
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
            // Deletar todos os artigos relacionados
            // Isso irá triggear o boot method do LawArticle que deleta as opções
            $legalReference->articles()->delete();
            
            // Deletar relacionamentos diretos na tabela user_legal_references
            // (já é tratado pelo onDelete('cascade') na migration, mas é bom garantir)
            DB::table('user_legal_references')
                ->where('legal_reference_id', $legalReference->id)
                ->delete();
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
