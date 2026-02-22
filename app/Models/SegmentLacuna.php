<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SegmentLacuna extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'legislation_segment_id',
        'word',
        'word_position',
        'is_correct',
        'gap_order',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'word_position' => 'integer',
        'gap_order' => 'integer',
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

    public function segment(): BelongsTo
    {
        return $this->belongsTo(LegislationSegment::class, 'legislation_segment_id');
    }
}
