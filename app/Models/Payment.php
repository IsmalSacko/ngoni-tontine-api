<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'tontine_id',
        'tontine_member_id',
        'cycle_id',
        'amount',
        'method',
        'validated_by',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function tontine()
    {
        return $this->belongsTo(Tontine::class);
    }

    public function member()
    {
        return $this->belongsTo(TontineMember::class, 'tontine_member_id');
    }

    public function cycle()
    {
        return $this->belongsTo(Cycle::class);
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
