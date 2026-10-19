<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;

class Tontine extends Model
{
    protected $fillable = [
        'name',
        'type',
        'amount',
        'target_amount',
        'currency',
        'deadline',
        'frequency',
        'period_count',
        'period_unit',
        'description',
        'start_date',
        'created_by',
        'group_id',
        'archived_at',
    ];

    // Fréquences disponibles
    /** Le pot va à un membre par tour. */
    const TYPE_ROTATING = 'rotating';

    /** Chacun cotise à intervalle fixe, l'argent reste en caisse (décès, rapatriement). */
    const TYPE_PERIODIC = 'periodic';

    /** Chacun donne ce qu'il peut, vers un objectif (mosquée, forage). */
    const TYPE_FUNDRAISING = 'fundraising';

    const TYPES = [self::TYPE_ROTATING, self::TYPE_PERIODIC, self::TYPE_FUNDRAISING];

    /** Monnaies proposées : celles de l'Afrique de l'Ouest et de la diaspora. */
    const CURRENCIES = ['XOF', 'XAF', 'GNF', 'EUR', 'USD', 'CAD', 'GBP', 'CHF', 'CNY', 'MAD', 'AED'];

    /** Une seule période : chacun doit sa part, qu'il verse petit à petit (château d'eau). */
    const FREQUENCY_ONCE = 'once';

    /** Tous les [period_count] [period_unit] : « tous les 2 mois ». */
    const FREQUENCY_CUSTOM = 'custom';

    const FREQUENCIES = ['daily', 'weekly', 'biweekly', 'monthly', 'quarterly', 'yearly', self::FREQUENCY_ONCE, self::FREQUENCY_CUSTOM];

    const PERIOD_UNITS = ['day', 'week', 'month', 'year'];

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
        'archived_at' => 'datetime',
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

    public function isOneTime(): bool
    {
        return $this->frequency === self::FREQUENCY_ONCE;
    }

    /**
     * Fin de la période qui commence le [$start] : la veille du début de la
     * suivante. Une cotisation « une seule fois » court jusqu'à sa date limite,
     * ou n'a pas de fin.
     */
    public function periodEndFrom(\Carbon\Carbon $start): ?\Carbon\Carbon
    {
        $start = $start->copy();

        return match ($this->frequency) {
            'daily' => $start,
            'weekly' => $start->addDays(6),
            'biweekly' => $start->addDays(13),
            'monthly' => $start->addMonth()->subDay(),
            'quarterly' => $start->addMonths(3)->subDay(),
            'yearly' => $start->addYear()->subDay(),
            self::FREQUENCY_ONCE => $this->deadline?->copy(),
            self::FREQUENCY_CUSTOM => match ($this->period_unit) {
                'day' => $start->addDays(max(1, (int) $this->period_count) - 1),
                'week' => $start->addWeeks(max(1, (int) $this->period_count))->subDay(),
                'year' => $start->addYears(max(1, (int) $this->period_count))->subDay(),
                default => $start->addMonths(max(1, (int) $this->period_count))->subDay(),
            },
            default => $start->addMonth()->subDay(),
        };
    }

    /**
     * Les caisses créées avant que le premier tour ne soit ouvert d'office
     * n'en ont aucun : on ne pouvait rien y encaisser. Il s'ouvre aujourd'hui.
     */
    public function ensureFirstCycle(): void
    {
        if ($this->cycles()->exists()) {
            return;
        }
        $start = \Carbon\Carbon::today();
        $this->cycles()->create([
            'cycle_number' => 1,
            'status' => 'active',
            'beneficiary_id' => null,
            'period_start' => $start->toDateString(),
            'period_end' => $this->periodEndFrom($start)?->toDateString(),
        ]);
    }

    /**
     * Supprime la caisse et tout ce qu'elle contient. Dans l'ordre : les tours
     * pointent vers leurs bénéficiaires, ils partent avant les membres.
     */
    public function purge(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            $this->payments()->delete();
            $this->expenses()->delete();
            $this->cycles()->delete();
            $this->members()->delete();
            $this->delete();
        });
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
            ->where('phone', 'like', '%'.Phone::tail($user->phone))
            ->get()
            ->contains(fn ($member) => Phone::same($member->phone, $user->phone));
    }
}
