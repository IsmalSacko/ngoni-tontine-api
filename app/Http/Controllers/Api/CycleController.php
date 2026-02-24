<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cycle;
use App\Models\Tontine;
use App\Models\TontineMember;
use Illuminate\Http\Request;

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

        // Autorisation de clôture:
        // - créateur de la tontine, OU
        // - président(e) identifié(e) par le même téléphone utilisateur
        // Note: la table tontine_members n'a pas de user_id.
        $user = auth()->user();
        $isCreator = (int) $tontine->created_by === (int) auth()->id();
        $isPresidentByPhone = $user
            ? $tontine->members()
                ->where('role', 'presidente')
                ->where('phone', $user->phone)
                ->exists()
            : false;

        if (!$isCreator && !$isPresidentByPhone) {
            return response()->json(['message' => 'Vous n\'avez pas l\'autorisation pour effectuer cette action.'], 403);
        }
        
        // Vérifier que le cycle est actif
        if ($cycle->status !== 'active') {
            return response()->json(['message' => 'Ce cycle est déjà clôturé'], 400);
        }
        
        // Vérifier que tous les membres ont payé (optionnel - peut être désactivé)
        $totalMembers = $tontine->members()->count();
        $totalPaid = $cycle->payments()->distinct('tontine_member_id')->count();
        
        if ($totalPaid < $totalMembers) {
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
        $nextBeneficiary = $this->getNextBeneficiary($tontine, $cycle);
        
        // Créer le cycle suivant
        $nextCycle = Cycle::create([
            'tontine_id' => $tontine->id,
            'cycle_number' => $cycle->cycle_number + 1,
            'beneficiary_id' => $nextBeneficiary?->id,
            'status' => 'active',
        ]);
        
        return response()->json([
            'message' => 'Cycle clôturé avec succès',
            'closed_cycle' => $cycle,
            'next_cycle' => $nextCycle->load('beneficiary'),
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
        if (!$nextBeneficiary) {
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
