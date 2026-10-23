<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Um lembrete enviado para quem parou de jogar, com o presente que ele leva.
 * Ver App\Services\ReengagementService.
 */
class ReengagementEmail extends Model
{
    public const GIFT_LIVES = 'lives';

    public const GIFT_XP_BOOST = 'xp_boost';

    protected $fillable = [
        'uuid',
        'user_id',
        'sequence',
        'gift',
        'inactive_days',
        'sent_at',
        'claimed_at',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'inactive_days' => 'integer',
        'sent_at' => 'datetime',
        'claimed_at' => 'datetime',
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
}
