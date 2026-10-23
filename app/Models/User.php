<?php

namespace App\Models;

use App\Services\ReengagementService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use Billable, HasApiTokens, HasFactory, Notifiable;

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
        'trial_ends_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        // Fica oculto de propósito: `GET /api/user` devolve o model cru e o
        // ChallengeController serializa o criador inteiro. O avatar é exposto
        // só onde a gente quer, e sempre por publicAvatarConfig(): no share do
        // Inertia, no ranking e, para o app, no UserResource e no ranking da API.
        'avatar_config',
        // Dados do onboarding web: ficam fora do JSON cru do `GET /api/user` para
        // o app Android continuar recebendo exatamente o que recebia.
        'onboarding_answers',
        'onboarding_completed_at',
        'welcome_offer_expires_at',
        // Lembretes por e-mail: também fora do JSON cru, pelo mesmo motivo.
        'email_opt_out_at',
        'xp_boost_until',
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
        'avatar_config' => 'array',
        'onboarding_answers' => 'array',
        'onboarding_completed_at' => 'datetime',
        'welcome_offer_expires_at' => 'datetime',
        'email_opt_out_at' => 'datetime',
        'xp_boost_until' => 'datetime',
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
            \Illuminate\Support\Facades\Log::warning('Erro ao verificar assinatura: '.$exception->getMessage());

            return false;
        }
    }

    /**
     * A oferta de boas-vindas do onboarding (15% no plano anual) ainda vale:
     * dentro das 24h e sem assinatura ativa.
     */
    public function hasWelcomeOffer(): bool
    {
        return $this->welcome_offer_expires_at !== null
            && $this->welcome_offer_expires_at->isFuture()
            && ! $this->hasActiveSubscription();
    }

    /**
     * O avatar como deve ser exibido: sem as peças exclusivas de assinante
     * (`avatar.premium`) se a assinatura não está mais ativa. Cada uma volta para
     * o primeiro id do grupo, que nunca é premium.
     *
     * O que está salvo não muda: se a pessoa voltar a assinar, a fantasia volta.
     * Use este método — e não o atributo cru — em tudo que manda o avatar para a
     * tela. Ao buscar vários usuários, carregue `subscriptions` junto para não
     * fazer uma consulta por usuário.
     *
     * @return array<string, string>|null
     */
    public function publicAvatarConfig(): ?array
    {
        $config = $this->avatar_config;

        if (! is_array($config) || $this->hasActiveSubscription()) {
            return $config;
        }

        foreach (config('avatar.premium', []) as $key => $ids) {
            if (isset($config[$key]) && in_array($config[$key], $ids, true)) {
                $config[$key] = config("avatar.shapes.{$key}")[0];
            }
        }

        return $config;
    }

    /**
     * publicAvatarConfig() de vários usuários de uma vez, indexado por id — é o
     * que as listas (os rankings da web e da API) usam. As assinaturas vêm junto
     * para não fazer uma consulta por usuário.
     *
     * @param  Collection<int, int>  $ids
     * @return Collection<int, array<string, string>|null>
     */
    public static function publicAvatarConfigsFor(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return static::with('subscriptions')
            ->whereIn('id', $ids)
            ->get(['id', 'avatar_config'])
            ->mapWithKeys(fn (User $user) => [$user->id => $user->publicAvatarConfig()]);
    }

    public function xpTransactions(): HasMany
    {
        return $this->hasMany(XpTransaction::class);
    }

    protected ?int $cachedXp = null;

    public function getXpAttribute(): int
    {
        if ($this->cachedXp === null) {
            $this->cachedXp = (int) $this->xpTransactions()->sum('amount');
        }

        return $this->cachedXp;
    }

    public function addXp(int $amount, string $sourceType, ?int $sourceId = null, ?int $legislationId = null): XpTransaction
    {
        $this->cachedXp = null;

        return $this->xpTransactions()->create([
            'amount' => $amount,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'legislation_id' => $legislationId,
        ]);
    }

    /** O boost de XP que vem de presente para assinante (ver ReengagementService) ainda vale. */
    public function hasXpBoost(): bool
    {
        return $this->xp_boost_until !== null && $this->xp_boost_until->isFuture();
    }

    /**
     * O XP de um exercício já com o boost, se houver. Os pontos onde o jogo dá
     * XP passam por aqui antes do addXp, e a resposta mostra o valor final.
     */
    public function boostedXp(int $amount): int
    {
        return $this->hasXpBoost()
            ? (int) round($amount * ReengagementService::BOOST_MULTIPLIER)
            : $amount;
    }

    public function receivesEmails(): bool
    {
        return $this->email_opt_out_at === null;
    }

    public function reengagementEmails(): HasMany
    {
        return $this->hasMany(ReengagementEmail::class);
    }

    /**
     * Acrescenta a última vez que a pessoa jogou em cada sistema: XP ganho, bloco
     * tentado no jogo novo e artigo tentado no legado. Leia com lastPlayedAt().
     */
    public function scopeWithLastPlayed(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('users.*');
        }

        return $query
            ->selectSub(DB::table('xp_transactions')->selectRaw('MAX(created_at)')->whereColumn('user_id', 'users.id'), 'last_xp_at')
            ->selectSub(DB::table('user_segment_progress')->selectRaw('MAX(updated_at)')->whereColumn('user_id', 'users.id'), 'last_segment_at')
            ->selectSub(DB::table('user_progress')->selectRaw('MAX(updated_at)')->whereColumn('user_id', 'users.id'), 'last_legacy_at');
    }

    /** Última jogada em qualquer sistema, ou nulo para quem nunca jogou. Pede scopeWithLastPlayed. */
    public function lastPlayedAt(): ?Carbon
    {
        $dates = array_filter([
            $this->getAttribute('last_xp_at'),
            $this->getAttribute('last_segment_at'),
            $this->getAttribute('last_legacy_at'),
        ]);

        return $dates ? Carbon::parse(max($dates)) : null;
    }

    public static function calculateXpGain(int $difficultyLevel): int
    {
        return $difficultyLevel * 5;
    }

    /**
     * Ofensiva atual (dias consecutivos de estudo), calculada a partir das
     * XpTransactions do usuário. Ver App\Services\StreakService.
     */
    public function effectiveStreak(): int
    {
        return app(\App\Services\StreakService::class)->currentStreak($this->id);
    }

    /**
     * Maior sequência de dias consecutivos já alcançada (histórico completo).
     */
    public function longestStreak(): int
    {
        return app(\App\Services\StreakService::class)->longestStreak($this->id);
    }

    /**
     * Indica se o usuário já concluiu um exercício hoje (no fuso da ofensiva).
     */
    public function playedToday(): bool
    {
        return app(\App\Services\StreakService::class)->playedToday($this->id);
    }
}
