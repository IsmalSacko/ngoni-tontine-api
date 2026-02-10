<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class Tontine extends Model
{
    protected $fillable = [
        'name',
        'amount',
        'frequency',
        'description',
        'start_date',
        'created_by',
    ];

    // Fréquences disponibles
    const FREQUENCY_DAILY = 'daily';
    const FREQUENCY_WEEKLY = 'weekly';
    const FREQUENCY_MONTHLY = 'monthly';
    const FREQUENCY_QUARTERLY = 'quarterly';
    const FREQUENCY_YEARLY = 'yearly';

    // Mapping des fréquences français -> anglais
    const FREQUENCY_MAP = [
        'Quotidienne' => self::FREQUENCY_DAILY,
        'Hebdomadaire' => self::FREQUENCY_WEEKLY,
        'Mensuelle' => self::FREQUENCY_MONTHLY,
        'Trimestrielle' => self::FREQUENCY_QUARTERLY,
        'Annuelle' => self::FREQUENCY_YEARLY,
    ];

    // Fréquences valides
    public static function getValidFrequencies(): array
    {
        return [
            self::FREQUENCY_DAILY,
            self::FREQUENCY_WEEKLY,
            self::FREQUENCY_MONTHLY,
            self::FREQUENCY_QUARTERLY,
            self::FREQUENCY_YEARLY,
        ];
    }

    // Attribut virtuel pour le nombre de membres
    protected $appends = ['members_count'];

    public function getMembersCountAttribute(): int
    {
        return $this->members()->count();
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->hasMany(TontineMember::class);
    }

    public function cycles()
    {
        return $this->hasMany(Cycle::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}

