<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sortie d'argent d'une caisse commune : un rapatriement, un achat de ciment.
 */
class Expense extends Model
{
    protected $fillable = [
        'tontine_id',
        'amount',
        'label',
        'note',
        'spent_at',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'spent_at' => 'date',
        ];
    }

    public function tontine(): BelongsTo
    {
        return $this->belongsTo(Tontine::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
