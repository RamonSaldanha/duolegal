<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Desafio de etapa do mapa beta: quais materiais de estudo a pessoa já concluiu dentro da
 * fase paralela de uma etapa. A etapa é identificada pelo seu último bloco.
 */
class UserBetaCheckpoint extends Model
{
    protected $fillable = [
        'user_id',
        'legislation_segment_id',
        'done_material_ids',
    ];

    protected $casts = [
        'done_material_ids' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<int, int> */
    public function doneIds(): array
    {
        return array_map('intval', $this->done_material_ids ?? []);
    }
}
