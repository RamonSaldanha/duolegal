<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StudyQuestion extends Model
{
    public const BOARDS = ['cebraspe', 'fgv', 'fcc', 'vunesp'];

    public const TYPE_TRUE_FALSE = 'true_false';

    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    /** Respostas possíveis em certo/errado. */
    public const TRUE_FALSE_ANSWERS = ['certo', 'errado'];

    /** Letras das alternativas em múltipla escolha. */
    public const OPTION_KEYS = ['A', 'B', 'C', 'D', 'E'];

    protected $fillable = [
        'uuid',
        'study_material_id',
        'position',
        'board',
        'type',
        'statement',
        'options',
        'correct_answer',
        'explanation',
        'legal_basis',
    ];

    protected $casts = [
        'options' => 'array',
        'position' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
        });
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(StudyMaterial::class, 'study_material_id');
    }
}
