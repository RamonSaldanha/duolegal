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

        \Illuminate\Support\Facades\Log::info('Verificando vidas para usuário ' . $this->id, [
            'lives' => $this->lives,
            'hasLives' => $hasLives,
            'hasInfiniteLives' => $hasInfiniteLives,
        ]);

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

        // Adiciona logs para depuração
        \Illuminate\Support\Facades\Log::info('Verificando vidas infinitas para usuário ' . $this->id, [
            'hasActiveSubscription' => $hasActiveSubscription,
        ]);

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

            \Illuminate\Support\Facades\Log::info('Verificando assinatura para usuário ' . $this->id, [
                'subscribed' => $subscribed
            ]);

            // Retorna apenas se o usuário tem uma assinatura ativa
            return $subscribed;
        } catch (\Exception $exception) {
            // Log do erro para depuração
            \Illuminate\Support\Facades\Log::warning('Erro ao verificar assinatura: ' . $exception->getMessage());
            return false;
        }
    }

    // Os métodos activateInfiniteLives e deactivateInfiniteLives foram removidos
    // pois agora as vidas infinitas são baseadas diretamente no status da assinatura
}
