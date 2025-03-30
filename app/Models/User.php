<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'lives'
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
    ];

    /**
     * Verifica se o usuário tem vidas disponíveis
     */
    public function hasLives(): bool
    {
        return $this->lives > 0;
    }

    /**
     * Remove uma vida do usuário
     */
    public function decrementLife(): void
    {
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
}
