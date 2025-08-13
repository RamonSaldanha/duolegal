<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChallengeProgress extends Model
{
    protected $table = 'challenge_progress';

    protected $fillable = [
        'challenge_id',
        'user_id',
        'law_article_id',
        'correct_answers',
        'wrong_answers',
        'percentage',
        'attempts',
        'best_score',
        'is_completed',
        'completed_at'
    ];

    protected $casts = [
        'percentage' => 'float',
        'attempts' => 'integer',
        'best_score' => 'integer',
        'wrong_answers' => 'integer',
        'correct_answers' => 'integer',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lawArticle(): BelongsTo
    {
        return $this->belongsTo(LawArticle::class);
    }

    // Método similar ao UserProgress::updateProgress mas para desafios
    public static function updateChallengeProgress($userId, $challengeId, $lawArticleId, $correctAnswers, $totalAnswers)
    {
        $percentage = $totalAnswers > 0 ? round(($correctAnswers / $totalAnswers) * 100, 2) : 0;
        
        // Busca progresso existente ou cria um novo
        $progress = self::firstOrNew([
            'user_id' => $userId,
            'challenge_id' => $challengeId,
            'law_article_id' => $lawArticleId
        ]);
        
        // Incrementa tentativas apenas se for um registro existente
        $isNewRecord = !$progress->exists;
        if (!$isNewRecord) {
            $progress->attempts += 1;
        } else {
            // Se for um novo registro, inicializa com 1
            $progress->attempts = 1;
            $progress->wrong_answers = 0;
        }
        
        // Verifica se o usuário acertou ou errou (baseado na porcentagem)
        if ($percentage >= 70) {
            // Se acertou, incrementa o contador de respostas corretas
            $progress->correct_answers = ($progress->correct_answers ?? 0) + 1;
        } else {
            // Se errou, incrementa o contador de respostas erradas
            $progress->wrong_answers = ($progress->wrong_answers ?? 0) + 1;
        }
        
        // Atualiza a porcentagem com base nos valores passados
        $progress->percentage = $percentage;
        
        // Atualizar pontuação máxima se a atual for maior
        if ($isNewRecord || $percentage > $progress->best_score) {
            $progress->best_score = $percentage;
        }
        
        // Marcar como concluído se a porcentagem for maior ou igual a 70%
        if ($percentage >= 70 && !$progress->is_completed) {
            $progress->is_completed = true;
            $progress->completed_at = now();
        }
        
        $progress->save();
        
        return $progress;
    }

    // Obter progresso do usuário em um desafio específico
    public static function getUserChallengeProgress($userId, $challengeId)
    {
        return self::where('user_id', $userId)
            ->where('challenge_id', $challengeId)
            ->get()
            ->keyBy('law_article_id');
    }

    // Verificar se usuário completou um desafio
    public static function isChallengeCompleted($userId, $challengeId)
    {
        $challenge = Challenge::findOrFail($challengeId);
        $completedArticles = self::where('user_id', $userId)
            ->where('challenge_id', $challengeId)
            ->where('is_completed', true)
            ->count();
        
        return $completedArticles >= $challenge->total_articles;
    }
}
