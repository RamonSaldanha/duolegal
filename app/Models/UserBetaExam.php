<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dia da prova de uma etapa do mapa beta, por pessoa: a tentativa em andamento (ou a última
 * entregue), as questões que já deram XP e o melhor resultado. A etapa é identificada pelo
 * seu último bloco.
 */
class UserBetaExam extends Model
{
    protected $fillable = [
        'user_id',
        'legislation_segment_id',
        'question_ids',
        'answers',
        'started_at',
        'deadline_at',
        'finished_at',
        'rewarded_question_ids',
        'best_correct',
        'attempts',
        'last_xp',
        'last_energy',
    ];

    protected $casts = [
        'question_ids' => 'array',
        'answers' => 'array',
        'rewarded_question_ids' => 'array',
        'started_at' => 'datetime',
        'deadline_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Há uma tentativa aberta: começou e ainda não foi entregue (o prazo pode já ter passado). */
    public function isOpen(): bool
    {
        return $this->started_at !== null && $this->finished_at === null;
    }

    /** Tentativa aberta e dentro do prazo. */
    public function isRunning(): bool
    {
        return $this->isOpen() && $this->deadline_at !== null && now()->lt($this->deadline_at);
    }

    /** @return array<int, int> */
    public function questionIds(): array
    {
        return array_map('intval', $this->question_ids ?? []);
    }

    /** @return array<int, int> */
    public function rewardedIds(): array
    {
        return array_map('intval', $this->rewarded_question_ids ?? []);
    }

    /**
     * Respostas da tentativa, por id da questão.
     *
     * @return array<int, array{answer: string, correct: bool}>
     */
    public function givenAnswers(): array
    {
        return $this->answers ?? [];
    }
}
