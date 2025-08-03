<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
class User extends Authenticatable
{
    use HasFactory, Notifiable, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'lives',
        'xp',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'lives' => 'integer',
        'xp' => 'integer',
        'has_infinite_lives' => 'boolean',
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Verifica se o usuário tem vidas disponíveis
     */
    public function hasLives(): bool
    {
        // Adiciona logs para depuração
        $hasInfiniteLives = $this->hasInfiniteLives();
        $hasLives = $this->lives > 0;

        // Se o usuário tem vidas infinitas, sempre retorna true
        if ($hasInfiniteLives) {
            return true;
        }

        return $hasLives;
    }

    /**
     * Remove uma vida do usuário
     */
    public function decrementLife(): void
    {
        // Se o usuário tem vidas infinitas, não decrementa
        if ($this->hasInfiniteLives()) {
            return;
        }

        // Só decrementa se tiver vidas disponíveis
        $this->decrement('lives', $this->lives > 0 ? 1 : 0);

        // Garante que lives nunca será menor que 0
        if ($this->lives < 0) {
            $this->lives = 0;
            $this->save();
        }
    }

    /**
     * Adiciona uma vida ao usuário
     */
    public function incrementLife(int $amount = 1): void
    {
        $this->increment('lives', $amount);
    }

    /**
     * Define o número de vidas do usuário
     */
    public function setLives(int $lives): void
    {
        $this->update(['lives' => max(0, $lives)]);
    }

    /**
     * Verifica se o usuário é admin
     */
    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    /**
     * Define a relação com referências legais
     */
    public function legalReferences()
    {
        return $this->belongsToMany(LegalReference::class, 'user_legal_references')
                    ->withTimestamps();
    }

    /**
     * Verifica se o usuário tem vidas infinitas (baseado apenas na assinatura ativa)
     */
    public function hasInfiniteLives(): bool
    {
        $hasActiveSubscription = $this->hasActiveSubscription();

        return $hasActiveSubscription;
    }

    /**
     * Verifica se o usuário tem uma assinatura ativa
     */
    public function hasActiveSubscription(): bool
    {
        try {
            // Verifica apenas se o usuário está inscrito no plano default
            $subscribed = $this->subscribed('default');
            // Retorna apenas se o usuário tem uma assinatura ativa
            return $subscribed;
        } catch (\Exception $exception) {
            // Log do erro para depuração
            \Illuminate\Support\Facades\Log::warning('Erro ao verificar assinatura: ' . $exception->getMessage());
            return false;
        }
    }

    /**
     * Adiciona XP ao usuário
     */
    public function addXp(int $xp): void
    {
        $this->increment('xp', $xp);
    }

    /**
     * Define o XP do usuário
     */
    public function setXp(int $xp): void
    {
        $this->update(['xp' => max(0, $xp)]);
    }

    /**
     * Calcula o XP ganho baseado na dificuldade do desafio
     */
    public static function calculateXpGain(int $difficultyLevel): int
    {
        // Sistema de XP baseado na dificuldade:
        // Nível 1 (Iniciante): 5 XP
        // Nível 2 (Básico): 10 XP  
        // Nível 3 (Intermediário): 15 XP
        // Nível 4 (Avançado): 20 XP
        // Nível 5 (Especialista): 25 XP
        return $difficultyLevel * 5;
    }

    // Os métodos activateInfiniteLives e deactivateInfiniteLives foram removidos
    // pois agora as vidas infinitas são baseadas diretamente no status da assinatura
}
