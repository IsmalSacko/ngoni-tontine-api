<?php

namespace App\Services;

use App\Models\Cycle;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Tontine;
use App\Models\TontineMember;
use Illuminate\Support\Collection;

/**
 * Situation de chaque membre d'un groupe dans chacune de ses caisses :
 * « Moussa : à jour en décès, doit 10 000 F pour la mosquée ».
 *
 * - Tontine rotative : la cotisation du tour en cours, si elle manque.
 * - Cotisation régulière : chaque période depuis l'inscription du membre ; les
 *   périodes closes impayées sont des retards, celle en cours est « à payer ».
 * - Part fixe : la part moins ce qui est déjà versé ; en retard après la date limite.
 * - Collecte : rien n'est dû, chacun donne ce qu'il peut ; on montre ce qu'il a donné.
 */
class GroupStatement
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forGroup(Group $group): array
    {
        $caisses = $group->caisses()->with(['cycles', 'payments'])->get();
        $enrollments = TontineMember::query()
            ->whereIn('tontine_id', $caisses->pluck('id'))
            ->whereNotNull('group_member_id')
            ->get()
            ->groupBy('group_member_id');

        return $group->members()->orderBy('name')->get()
            ->map(fn (GroupMember $member) => $this->forMember($member, $caisses, $enrollments->get($member->id, collect())))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Tontine>  $caisses
     * @param  Collection<int, TontineMember>  $enrollments
     * @return array<string, mixed>
     */
    private function forMember(GroupMember $member, Collection $caisses, Collection $enrollments): array
    {
        $lines = [];

        foreach ($enrollments as $enrollment) {
            $caisse = $caisses->firstWhere('id', $enrollment->tontine_id);
            if ($caisse === null) {
                continue;
            }
            $lines[] = $this->line($caisse, $enrollment);
        }

        $late = array_sum(array_column($lines, 'late_amount'));
        $current = array_sum(array_column($lines, 'current_due'));

        return [
            'member' => $member,
            'paid_total' => array_sum(array_column($lines, 'paid_total')),
            'late_amount' => $late,
            'current_due' => $current,
            'status' => $this->status($late, $current),
            'caisses' => $lines,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function line(Tontine $caisse, TontineMember $enrollment): array
    {
        $payments = $caisse->payments->where('tontine_member_id', $enrollment->id);
        $paidIn = fn (Cycle $cycle): int => (int) $payments->where('cycle_id', $cycle->id)->sum('amount');
        $late = 0;
        $current = 0;
        $active = $enrollment->status !== 'paused';

        if ($active && $caisse->type === Tontine::TYPE_PERIODIC) {
            // Inscrit à la création de la caisse : il doit tout depuis son début,
            // même antidaté. Arrivé plus tard : rien d'avant son arrivée.
            $joinedLater = $enrollment->created_at && $caisse->created_at
                && $enrollment->created_at->gt($caisse->created_at->copy()->addHour());
            $joined = $joinedLater ? $enrollment->created_at->copy()->startOfDay() : null;
            foreach ($caisse->cycles as $cycle) {
                // Une période close avant l'inscription du membre ne lui est pas
                // due ; celle en cours l'est par tous ceux qui sont là.
                if ($joined && $cycle->status !== 'active' && $cycle->period_end
                    && $cycle->period_end->copy()->endOfDay()->lt($joined)) {
                    continue;
                }
                $missing = max(0, $caisse->amount - $paidIn($cycle));
                if ($cycle->status === 'active') {
                    $current += $missing;
                } else {
                    $late += $missing;
                }
            }
        }

        // Part fixe : il doit sa part, moins ce qu'il a déjà versé. Passé la date
        // limite, le reste est un retard.
        if ($active && $caisse->type === Tontine::TYPE_SHARE) {
            $remaining = max(0, $caisse->amount - (int) $payments->sum('amount'));
            if ($caisse->deadline && $caisse->deadline->copy()->endOfDay()->isPast()) {
                $late = $remaining;
            } else {
                $current = $remaining;
            }
        }

        if ($active && $caisse->isRotating()) {
            $cycle = $caisse->cycles->where('status', 'active')->sortByDesc('cycle_number')->first();
            if ($cycle !== null) {
                $current = max(0, $caisse->amount - $paidIn($cycle));
            }
        }

        return [
            'tontine_id' => $caisse->id,
            'name' => $caisse->name,
            'type' => $caisse->type,
            'currency' => $caisse->currency ?? 'XOF',
            'paid_total' => (int) $payments->sum('amount'),
            'late_amount' => $late,
            'current_due' => $current,
            'status' => $this->status($late, $current),
        ];
    }

    private function status(int $late, int $current): string
    {
        return match (true) {
            $late > 0 => 'en_retard',
            $current > 0 => 'a_payer',
            default => 'a_jour',
        };
    }
}
