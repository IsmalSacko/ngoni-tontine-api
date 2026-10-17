<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande d'adhésion, née d'un QR code scanné, en attente du bureau.
 */
class JoinRequest extends Model
{
    const PENDING = 'pending';

    const APPROVED = 'approved';

    const REJECTED = 'rejected';

    protected $fillable = ['invitation_id', 'user_id', 'name', 'phone', 'status', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
