<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uma linha do livro da energia (ver EnergyService). */
class EnergyTransaction extends Model
{
    const UPDATED_AT = null;

    // Motivos
    const PHASE = 'phase';

    const FAIL = 'fail';

    const COMBO = 'combo';

    const REVIEW = 'review';

    const CHALLENGE = 'challenge';

    const EXAM = 'exam';

    const EXAM_RETAKE = 'exam_retake';

    const AD = 'ad';

    const GIFT = 'gift';

    protected $fillable = ['user_id', 'amount', 'reason'];

    protected $casts = ['amount' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
