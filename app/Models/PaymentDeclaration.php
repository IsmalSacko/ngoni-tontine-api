<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paiement déclaré par un membre, en attente de la validation du bureau.
 */
class PaymentDeclaration extends Model
{
    const PENDING = 'pending';

    const APPROVED = 'approved';

    const REJECTED = 'rejected';

    protected $fillable = [
        'tontine_id', 'tontine_member_id', 'cycle_id', 'user_id', 'amount', 'method',
        'paid_at', 'reference', 'proof_path', 'status', 'decision_note', 'decided_by',
        'decided_at', 'payment_id',
    ];

    protected $hidden = ['proof_path'];

    protected $appends = ['has_proof'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public function getHasProofAttribute(): bool
    {
        return $this->proof_path !== null;
    }

    public function tontine(): BelongsTo
    {
        return $this->belongsTo(Tontine::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(TontineMember::class, 'tontine_member_id');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }
}
