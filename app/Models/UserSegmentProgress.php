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
     * Atualiza o progresso do usuário em um segmento.
     */
    public static function updateProgress(int $userId, int $segmentId, int $correctCount, int $totalCount, array $answersData): self
    {
        $percentage = $totalCount > 0 ? round(($correctCount / $totalCount) * 100, 2) : 100;

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

        if ($percentage >= 70) {
            $progress->correct_answers += 1;
        } else {
            $progress->wrong_answers += 1;
        }

        if ($isNew || $percentage > $progress->best_score) {
            $progress->best_score = $percentage;
        }

        if ($percentage >= 70 && ! $progress->is_completed) {
            $progress->is_completed = true;
            $progress->completed_at = now();
        }

        $progress->answers = $answersData;
        $progress->save();

        return $progress;
    }
}
