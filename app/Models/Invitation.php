<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Invitation à rejoindre un groupe ou une caisse, portée par un QR code.
 */
class Invitation extends Model
{
    protected $fillable = ['token', 'group_id', 'tontine_id', 'created_by', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public static function newToken(): string
    {
        return Str::lower(Str::random(24));
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function tontine(): BelongsTo
    {
        return $this->belongsTo(Tontine::class);
    }

    /** Le groupe ou la caisse visé, pour l'afficher et l'administrer. */
    public function target(): Group|Tontine|null
    {
        return $this->group ?? $this->tontine;
    }

    public function isManagedBy(User $user): bool
    {
        return (bool) $this->target()?->isManagedBy($user);
    }

    /** Adresse du lien d'invitation (page web qui ouvre l'app ou les boutiques). */
    public function url(): string
    {
        return rtrim(config('app.url'), '/').'/rejoindre/'.$this->token;
    }
}
