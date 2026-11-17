<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LegislationSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'legislation_id',
        'original_text',
        'char_start',
        'char_end',
        'segment_type',
        'article_reference',
        'structural_marker',
        'position',
        'is_block',
    ];

    protected $casts = [
        'is_block' => 'boolean',
        'position' => 'integer',
        'char_start' => 'integer',
        'char_end' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
        });
    }

    public function legislation(): BelongsTo
    {
        return $this->belongsTo(Legislation::class);
    }

    public function lacunas(): HasMany
    {
        return $this->hasMany(SegmentLacuna::class);
    }

    /**
     * Lacunas visíveis no jogo. As ocultas (is_active = false) continuam guardadas.
     */
    public function activeLacunas(): HasMany
    {
        return $this->hasMany(SegmentLacuna::class)->where('is_active', true);
    }

    /**
     * Rótulo do dispositivo vetado ou revogado ("Art. 194", "§ 4º", "IX-A"), ou null.
     *
     * A importação tira o texto riscado e as marcas "(VETADO)" e "(Revogado)", então o bloco
     * fica só com o rótulo e a pontuação ("IX-A -;", "§ 4º ."). Não dá para saber qual dos
     * dois foi, só que o dispositivo não tem mais texto.
     */
    public function historicalLabel(): ?string
    {
        $text = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $this->original_text));

        $label = '(?:'
            .'art\.?\s*\d[\d.]*\s*[º°o]?(?:\s*-\s*(?-i:[A-Z]{1,3}))?'
            .'|§\s*\d+\s*[º°o]?(?:\s*-\s*(?-i:[A-Z]{1,3}))?'
            .'|par[áa]grafo\s+[úu]nico'
            .'|(?-i:[IVXLCDM]+)(?:\s*-\s*(?-i:[A-Z]{1,3}))?'
            .'|(?-i:[a-z])\)'
            .')';

        if (! preg_match('/^('.$label.')[\s.;:,\-–—]*$/iu', $text, $match)) {
            return null;
        }

        return rtrim($match[1], ' .');
    }
}
