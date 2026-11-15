<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStudyMaterial extends Model
{
    protected $fillable = [
        'user_id',
        'study_material_id',
        'answers',
        'correct_count',
        'completed_at',
    ];

    protected $casts = [
        'answers' => 'array',
        'correct_count' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(StudyMaterial::class, 'study_material_id');
    }
}
