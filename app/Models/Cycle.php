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
        'period_start',
        'period_end',
        'completed_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
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
