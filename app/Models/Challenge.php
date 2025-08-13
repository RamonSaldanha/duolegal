<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Challenge extends Model
{
    protected $fillable = [
        'uuid',
        'title',
        'description',
        'created_by',
        'selected_articles',
        'is_public',
        'is_active',
        'total_articles'
    ];

    protected $casts = [
        'selected_articles' => 'array',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'total_articles' => 'integer'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
            // Calcular total_articles baseado no array selected_articles
            if (is_array($model->selected_articles)) {
                $model->total_articles = count($model->selected_articles);
            }
        });

        static::updating(function ($model) {
            // Recalcular total_articles ao atualizar
            if (is_array($model->selected_articles)) {
                $model->total_articles = count($model->selected_articles);
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'challenge_progress')
                    ->withPivot(['law_article_id', 'is_completed', 'best_score', 'attempts', 'created_at', 'updated_at']);
    }

    public function challengeProgress(): HasMany
    {
        return $this->hasMany(ChallengeProgress::class);
    }

    // Obter artigos do desafio com suas relações
    public function getSelectedArticles()
    {
        if (empty($this->selected_articles)) {
            return collect();
        }

        return LawArticle::with(['options', 'legalReference'])
            ->whereIn('id', $this->selected_articles)
            ->orderByRaw('FIELD(id, ' . implode(',', $this->selected_articles) . ')')
            ->get();
    }

    // Verificar se usuário pode acessar o desafio
    public function canAccess(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        return $this->is_active && ($this->is_public || $this->created_by === $user->id);
    }

    // Obter estatísticas do desafio
    public function getStats()
    {
        $totalParticipants = $this->challengeProgress()->distinct('user_id')->count();
        
        // Contar usuários que completaram o desafio (têm todos os artigos marcados como completos)
        $usersWithProgress = $this->challengeProgress()
            ->selectRaw('user_id, COUNT(*) as completed_articles')
            ->where('is_completed', true)
            ->groupBy('user_id')
            ->having('completed_articles', '>=', $this->total_articles)
            ->get();
        
        $completedCount = $usersWithProgress->count();
        
        return [
            'total_participants' => $totalParticipants,
            'completed_count' => $completedCount,
            'completion_rate' => $totalParticipants > 0 ? round(($completedCount / $totalParticipants) * 100, 2) : 0
        ];
    }

    // Scope para desafios públicos
    public function scopePublic($query)
    {
        return $query->where('is_public', true)->where('is_active', true);
    }

    // Scope para desafios do usuário
    public function scopeByUser($query, $userId)
    {
        return $query->where('created_by', $userId);
    }
}
