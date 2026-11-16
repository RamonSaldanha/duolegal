<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Desafio de um quadrado do mapa beta: quais materiais de estudo a pessoa já concluiu no
 * último passo do quadrado. O quadrado é identificado pelo seu último bloco.
 */
class UserBetaCheckpoint extends Model
{
    protected $fillable = [
        'user_id',
        'legislation_segment_id',
        'done_material_ids',
        'in_progress',
    ];

    protected $casts = [
        'done_material_ids' => 'array',
        'in_progress' => 'array',
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

    /**
     * O que já foi feito de um material ainda não concluído neste quadrado (para a pessoa
     * voltar de onde parou). Ao concluir, o material sai daqui e entra em done_material_ids.
     *
     * @return array{summary: bool, answers: array<int, string>}
     */
    public function partial(int $materialId): array
    {
        $partial = $this->in_progress[$materialId] ?? [];

        return ['summary' => (bool) ($partial['summary'] ?? false), 'answers' => $partial['answers'] ?? []];
    }
}
