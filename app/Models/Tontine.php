<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tontine extends Model
{
    protected $fillable = [
        'name',
        'type',
        'amount',
        'target_amount',
        'deadline',
        'frequency',
        'description',
        'start_date',
        'created_by',
        'group_id',
    ];

    // Fréquences disponibles
    /** Le pot va à un membre par tour. */
    const TYPE_ROTATING = 'rotating';

    /** Chacun cotise à intervalle fixe, l'argent reste en caisse (décès, rapatriement). */
    const TYPE_PERIODIC = 'periodic';

    /** Chacun donne ce qu'il peut, vers un objectif (mosquée, forage). */
    const TYPE_FUNDRAISING = 'fundraising';

    /**
     * Chacun doit une part fixe, une fois, qu'il verse petit à petit (château
     * d'eau : 200 000 F par membre).
     */
    const TYPE_SHARE = 'share';

    const TYPES = [self::TYPE_ROTATING, self::TYPE_PERIODIC, self::TYPE_FUNDRAISING, self::TYPE_SHARE];

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
    protected $casts = [
        'deadline' => 'date',
        'target_amount' => 'integer',
    ];

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

    public function group(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function expenses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function isRotating(): bool
    {
        return ($this->type ?? self::TYPE_ROTATING) === self::TYPE_ROTATING;
    }

    /**
     * Peut gérer la caisse : sa créatrice, ou un membre de son bureau reconnu
     * à son téléphone (les membres n'ont pas de compte).
     *
     * @param  list<string>  $roles
     */
    public function isManagedBy(User $user, array $roles = ['presidente', 'tresoriere']): bool
    {
        if ((int) $this->created_by === (int) $user->id) {
            return true;
        }

        return $this->members()
            ->whereIn('role', $roles)
            ->where('phone', $user->phone)
            ->exists();
    }
}
