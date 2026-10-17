<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetBeneficiaryRequest;
use App\Models\Cycle;
use App\Models\Tontine;
use App\Models\TontineMember;
use Carbon\Carbon;

class CycleController extends Controller
{
    /**
     * Obtenir le résumé du cycle actif (qui a payé, qui n'a pas payé)
     */
    public function summary(Tontine $tontine, Cycle $cycle)
    {
        $members = $tontine->members()->orderBy('order_position')->get();
        $payments = $cycle->payments()->with('member')->get();

        $summary = $members->map(function ($member) use ($payments) {
            $memberPayment = $payments->firstWhere('tontine_member_id', $member->id);

            return [
                'member' => $member,
                'has_paid' => $memberPayment !== null,
                'payment' => $memberPayment,
            ];
        });

        $totalPaid = $payments->pluck('tontine_member_id')->unique()->count();
        $totalMembers = $members->count();
        $isComplete = $totalPaid >= $totalMembers;

        return response()->json([
            'cycle' => $cycle,
            'summary' => $summary,
            'total_paid' => $totalPaid,
            'total_members' => $totalMembers,
            'is_complete' => $isComplete,
        ]);
    }

    /**
     * Clôturer le cycle actuel et créer le suivant
     */
    public function close(Tontine $tontine, Cycle $cycle)
    {
        // Vérifier que le cycle appartient bien à la tontine
        if ((int) $cycle->tontine_id !== (int) $tontine->id) {
            return response()->json(['message' => 'Cycle introuvable pour cette tontine'], 404);
        }

        // Clôture : la créatrice ou la présidente.
        if (! $tontine->isManagedBy(auth()->user(), ['presidente'])) {
            return response()->json(['message' => 'Vous n\'avez pas l\'autorisation pour effectuer cette action.'], 403);
        }

        // Vérifier que le cycle est actif
        if ($cycle->status !== 'active') {
            return response()->json(['message' => 'Ce cycle est déjà clôturé'], 400);
        }

        // Vérifier que tous les membres ont payé (optionnel - peut être désactivé)
        $totalMembers = $tontine->members()->count();
        $totalPaid = $cycle->payments()->distinct('tontine_member_id')->count();

        // Une tontine rotative attend tout le monde : le pot est promis entier
        // au bénéficiaire. Une cotisation régulière passe à la période suivante,
        // les retards restent dus.
        if ($tontine->isRotating() && $totalPaid < $totalMembers) {
            return response()->json([
                'message' => 'Tous les membres n\'ont pas encore payé',
                'total_members' => $totalMembers,
                'total_paid' => $totalPaid,
            ], 400);
        }

        // Clôturer le cycle actuel
        $cycle->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Déterminer le prochain bénéficiaire
        $nextBeneficiary = $tontine->isRotating() ? $this->getNextBeneficiary($tontine, $cycle) : null;

        $currentPeriodStart = $cycle->period_start
            ? Carbon::parse($cycle->period_start)->startOfDay()
            : ($cycle->created_at
                ? Carbon::parse($cycle->created_at)->startOfDay()
                : Carbon::today());
        $currentPeriodEnd = $cycle->period_end
            ? Carbon::parse($cycle->period_end)->endOfDay()
            : ($tontine->periodEndFrom($currentPeriodStart) ?? Carbon::today())->endOfDay();

        // Compléter les champs de période du cycle courant si absents
        if (! $cycle->period_start || ! $cycle->period_end) {
            $cycle->update([
                'period_start' => $currentPeriodStart->toDateString(),
                'period_end' => $currentPeriodEnd->toDateString(),
            ]);
        }

        $nextPeriodStart = $currentPeriodEnd->copy()->addDay()->startOfDay();
        $nextPeriodEnd = $tontine->periodEndFrom($nextPeriodStart);

        // Créer le cycle suivant
        $nextCycle = Cycle::create([
            'tontine_id' => $tontine->id,
            'cycle_number' => $cycle->cycle_number + 1,
            'beneficiary_id' => $nextBeneficiary?->id,
            'status' => 'active',
            'period_start' => $nextPeriodStart->toDateString(),
            'period_end' => $nextPeriodEnd?->toDateString(),
        ]);

        return response()->json([
            'message' => 'Cycle clôturé avec succès',
            'closed_cycle' => $cycle,
            'next_cycle' => $nextCycle->load('beneficiary'),
        ]);
    }

    /**
     * Désigner le bénéficiaire du tour en cours.
     *
     * Le tour 1 n'en reçoit pas à la création, et un groupe peut décider d'un
     * autre ordre que celui des positions : la présidente ou la créatrice choisit.
     */
    public function setBeneficiary(SetBeneficiaryRequest $request, Tontine $tontine, Cycle $cycle)
    {
        if ((int) $cycle->tontine_id !== (int) $tontine->id) {
            return response()->json(['message' => 'Cycle introuvable pour cette tontine'], 404);
        }

        if (! $tontine->isManagedBy(auth()->user(), ['presidente'])) {
            return response()->json(['message' => 'Vous n\'avez pas l\'autorisation pour effectuer cette action.'], 403);
        }

        if ($cycle->status !== 'active') {
            return response()->json(['message' => 'Ce cycle est déjà clôturé'], 400);
        }

        $cycle->update(['beneficiary_id' => $request->integer('member_id')]);

        return response()->json([
            'message' => 'Bénéficiaire désigné',
            'cycle' => $cycle->load('beneficiary'),
        ]);
    }

    /**
     * Déterminer le prochain bénéficiaire selon l'ordre de position
     */
    private function getNextBeneficiary(Tontine $tontine, Cycle $currentCycle)
    {
        // Récupérer tous les bénéficiaires précédents
        $previousBeneficiaries = Cycle::where('tontine_id', $tontine->id)
            ->whereNotNull('beneficiary_id')
            ->pluck('beneficiary_id')
            ->toArray();

        // Trouver le premier membre qui n'a pas encore été bénéficiaire
        $nextBeneficiary = TontineMember::where('tontine_id', $tontine->id)
            ->whereNotIn('id', $previousBeneficiaries)
            ->orderBy('order_position')
            ->first();

        // Si tous ont été bénéficiaires, recommencer depuis le premier
        if (! $nextBeneficiary) {
            $nextBeneficiary = TontineMember::where('tontine_id', $tontine->id)
                ->orderBy('order_position')
                ->first();
        }

        return $nextBeneficiary;
    }

    /**
     * Obtenir l'historique des cycles
     */
    public function index(Tontine $tontine)
    {
        $cycles = $tontine->cycles()
            ->with('beneficiary')
            ->orderBy('cycle_number', 'desc')
            ->get();

        return response()->json(['data' => $cycles]);
    }
}
