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
}
