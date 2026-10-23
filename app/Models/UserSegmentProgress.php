<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserSegmentProgress extends Model
{
    protected $table = 'user_segment_progress';

    protected $fillable = [
        'uuid',
        'user_id',
        'legislation_segment_id',
        'correct_answers',
        'wrong_answers',
        'attempts',
        'best_score',
        'answers',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'correct_answers' => 'integer',
        'wrong_answers' => 'integer',
        'attempts' => 'integer',
        'best_score' => 'float',
        'answers' => 'array',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(LegislationSegment::class, 'legislation_segment_id');
    }

    /**
     * Acertos necessários para passar: o aluno pode errar no máximo 1 lacuna
     * (bloco de 1 lacuna exige o acerto; 2 → 1; 3 → 2; 4 → 3; 5 → 4). Nunca exige
     * mais que os 70% da regra antiga, para blocos com muitas lacunas não ficarem mais difíceis.
     */
    public static function requiredCorrect(int $totalCount): int
    {
        if ($totalCount <= 0) {
            return 0;
        }
        if ($totalCount === 1) {
            return 1;
        }

        return min($totalCount - 1, (int) ceil($totalCount * 0.7));
    }

    public static function passed(int $correctCount, int $totalCount): bool
    {
        return $correctCount >= self::requiredCorrect($totalCount);
    }

    /**
     * Atualiza o progresso do usuário em um segmento.
     */
    public static function updateProgress(int $userId, int $segmentId, int $correctCount, int $totalCount, array $answersData): self
    {
        $percentage = $totalCount > 0 ? round(($correctCount / $totalCount) * 100, 2) : 100;
        $passed = self::passed($correctCount, $totalCount);

        $progress = self::firstOrNew([
            'user_id' => $userId,
            'legislation_segment_id' => $segmentId,
        ]);

        $isNew = ! $progress->exists;

        if ($isNew) {
            $progress->attempts = 1;
            $progress->correct_answers = 0;
            $progress->wrong_answers = 0;
        } else {
            $progress->attempts += 1;
        }

        if ($passed) {
            $progress->correct_answers += 1;
        } else {
            $progress->wrong_answers += 1;
        }

        if ($isNew || $percentage > $progress->best_score) {
            $progress->best_score = $percentage;
        }

        if ($passed && ! $progress->is_completed) {
            $progress->is_completed = true;
            $progress->completed_at = now();
        }

        $progress->answers = $answersData;
        $progress->save();

        return $progress;
    }
}
