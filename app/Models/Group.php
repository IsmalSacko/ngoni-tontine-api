<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Association de personnes qui tient plusieurs caisses avec les mêmes membres.
 */
class Group extends Model
{
    protected $fillable = ['name', 'description', 'currency', 'created_by', 'archived_at'];

    protected $casts = ['archived_at' => 'datetime'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function caisses(): HasMany
    {
        return $this->hasMany(Tontine::class);
    }

    /**
     * Peut gérer le groupe : son créateur, ou un membre de son bureau reconnu à
     * son téléphone.
     */
    public function isManagedBy(User $user): bool
    {
        if ((int) $this->created_by === (int) $user->id) {
            return true;
        }

        return $this->members()
            ->whereIn('role', ['presidente', 'tresoriere'])
            ->where('phone', 'like', '%'.Phone::tail($user->phone))
            ->get()
            ->contains(fn ($member) => Phone::same($member->phone, $user->phone));
    }

    /** Supprime le groupe, ses caisses et leur historique. */
    public function purge(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            foreach ($this->caisses as $caisse) {
                $caisse->purge();
            }
            $this->delete();
        });
    }

    /**
     * Inscrit [$member] dans une caisse du groupe, à la dernière place, s'il n'y
     * est pas déjà.
     */
    public function enroll(GroupMember $member, Tontine $caisse): void
    {
        if ($caisse->members()->where('group_member_id', $member->id)->exists()) {
            return;
        }

        $caisse->members()->create([
            'group_member_id' => $member->id,
            'name' => $member->name,
            'phone' => $member->phone,
            'role' => $member->role,
            'order_position' => (int) $caisse->members()->max('order_position') + 1,
        ]);
    }
}
