<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Um trecho contínuo de blocos de uma lei, escolhido pelo agente de IA: com material de
 * estudo (rascunho ou publicado) ou pulado. Ver StudyMaterialService.
 */
class StudyMaterial extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'uuid',
        'legislation_id',
        'first_block_id',
        'last_block_id',
        'block_ids',
        'content_hash',
        'title',
        'summary',
        'status',
        'skip_reason',
        'generated_by_model',
        'skill_version',
        'published_at',
    ];

    protected $casts = [
        'block_ids' => 'array',
        'published_at' => 'datetime',
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

    public function questions(): HasMany
    {
        return $this->hasMany(StudyQuestion::class)->orderBy('position');
    }

    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    /** @return array<int, int> */
    public function coveredBlockIds(): array
    {
        return array_map('intval', $this->block_ids ?? []);
    }
}
