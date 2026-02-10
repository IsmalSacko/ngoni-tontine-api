<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cycle extends Model
{
    protected $fillable = [
        'tontine_id',
        'cycle_number',
        'beneficiary_id',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function tontine()
    {
        return $this->belongsTo(Tontine::class);
    }

    public function beneficiary()
    {
        return $this->belongsTo(TontineMember::class, 'beneficiary_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}

