<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UserProgress extends Model
{
    use HasFactory;

    /**
     * Nome da tabela associada ao modelo
     */
    protected $table = 'user_progress';

    /**
     * Os atributos que podem ser atribuídos em massa
     */
    protected $fillable = [
        'user_id',
        'law_article_id',
        'correct_answers',
        'percentage',
        'attempts',
        'best_score',
        'wrong_answers',
        'revisions',
        'completed_at',
        'is_completed'
    ];

    /**
     * Os atributos que devem ser convertidos para tipos nativos
     */
    protected $casts = [
        'percentage' => 'float',
        'attempts' => 'integer',
        'best_score' => 'integer',
        'wrong_answers' => 'integer',
        'revisions' => 'integer',
        'completed_at' => 'datetime',
        'is_completed' => 'boolean',
    ];

    /**
     * Boot do modelo
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Gerar UUID se não estiver definido
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Relacionamento com o usuário
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relacionamento com o artigo de lei
     */
    public function lawArticle()
    {
        return $this->belongsTo(LawArticle::class);
    }

    /**
     * Atualiza o progresso do usuário em um artigo específico
     */
    public static function updateProgress($userId, $lawArticleId, $correctAnswers, $totalAnswers)
    {
        $percentage = $totalAnswers > 0 ? round(($correctAnswers / $totalAnswers) * 100, 2) : 0;
        
        // Busca progresso existente ou cria um novo
        $progress = self::firstOrNew([
            'user_id' => $userId,
            'law_article_id' => $lawArticleId
        ]);
        
        // Incrementa tentativas apenas se for um registro existente
        $isNewRecord = !$progress->exists;
        if (!$isNewRecord) {
            $progress->attempts += 1;
            
            // Se o usuário já completou o artigo antes e está revisando
            if ($progress->is_completed) {
                $progress->revisions += 1;
            }
        } else {
            // Se for um novo registro, inicializa com 1
            $progress->attempts = 1;
            $progress->wrong_answers = 0;
            $progress->revisions = 0;
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
        // Não armazenamos mais total_answers, apenas usamos para cálculo
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
}
