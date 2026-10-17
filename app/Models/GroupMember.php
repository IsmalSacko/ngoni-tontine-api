<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupMember extends Model
{
    protected $fillable = ['group_id', 'name', 'phone', 'role'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** Ses inscriptions dans les caisses du groupe. */
    public function enrollments(): HasMany
    {
        return $this->hasMany(TontineMember::class);
    }

    /** Le numéro s'enregistre sans espaces ni tirets : voir [Phone::clean()]. */
    protected function phone(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            set: fn (?string $value) => \App\Support\Phone::clean($value),
        );
    }
}
