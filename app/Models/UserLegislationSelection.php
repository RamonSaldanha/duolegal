<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLegislationSelection extends Model
{
    protected $fillable = [
        'user_id',
        'legislation_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function legislation(): BelongsTo
    {
        return $this->belongsTo(Legislation::class);
    }
}
