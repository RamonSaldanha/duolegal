<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Legislation extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'legal_reference_id',
        'title',
        'source_url',
        'raw_text',
        'status',
        'created_by',
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

    public function segments(): HasMany
    {
        return $this->hasMany(LegislationSegment::class);
    }

    public function legalReference(): BelongsTo
    {
        return $this->belongsTo(LegalReference::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
