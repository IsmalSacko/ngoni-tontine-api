<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TontineMember extends Model
{
    protected $fillable = [
        'tontine_id',
        'group_member_id',
        'name',
        'phone',
        'role',
        'order_position', // Position de l'ordre de paiement
        'status', // active, paused, inactive
    ];

    public function tontine()
    {
        return $this->belongsTo(Tontine::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /** Le numéro s'enregistre sans espaces ni tirets : voir [Phone::clean()]. */
    protected function phone(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            set: fn (?string $value) => \App\Support\Phone::clean($value),
        );
    }
}
