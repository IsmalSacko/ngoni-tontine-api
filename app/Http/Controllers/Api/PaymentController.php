<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Models\Tontine;

class PaymentController extends Controller
{
    public function index(Tontine $tontine)
    {
        return $tontine->payments()->with('member')->orderBy('paid_at', 'desc')->get();
    }

    public function store(StorePaymentRequest $request)
    {
        // Loguer les données reçues pour débogage
        \Log::info('Payment request received:', $request->all());

        $tontineId = $request->input('tontine_id');
        if (! Tontine::find($tontineId)->isManagedBy($request->user())) {
            return response()->json(['message' => 'Seul le bureau de la caisse peut encaisser.'], 403);
        }
        $cycleId = $request->input('cycle_id');

        // Récupérer ou créer le cycle pour cette tontine
        if (! $cycleId) {
            // Si aucun cycle fourni, utiliser ou créer le cycle 1
            $cycle = \App\Models\Cycle::firstOrCreate(
                ['tontine_id' => $tontineId, 'cycle_number' => 1],
                ['beneficiary_id' => null]
            );
            $cycleId = $cycle->id;
            \Log::info('Cycle created/retrieved:', ['cycle_id' => $cycleId]);
        } else {
            // Vérifier que le cycle existe, sinon créer le cycle 1
            $cycle = \App\Models\Cycle::find($cycleId);
            if (! $cycle) {
                $cycle = \App\Models\Cycle::firstOrCreate(
                    ['tontine_id' => $tontineId, 'cycle_number' => 1],
                    ['beneficiary_id' => null]
                );
                $cycleId = $cycle->id;
                \Log::info('Cycle not found, created new one:', ['cycle_id' => $cycleId]);
            }
        }

        $payment = Payment::create([
            'tontine_id' => $tontineId,
            'tontine_member_id' => $request->input('tontine_member_id'),
            'cycle_id' => $cycleId,
            'amount' => $request->input('amount'),
            'method' => $request->input('method'),
            'validated_by' => auth()->id(),
            // Un paiement ancien se saisit à sa vraie date : les caisses arrivent
            // dans l'app avec des années d'historique.
            'paid_at' => $request->filled('paid_at') ? $request->date('paid_at')->setTimeFrom(now()) : now(),
        ]);

        \Log::info('Payment created successfully:', ['payment_id' => $payment->id]);

        return $payment;
    }

    /**
     * Annule un paiement saisi par erreur : le bureau. La déclaration qui l'a
     * fait entrer, s'il y en a une, passe en refusée : le membre le voit.
     */
    public function destroy(Payment $payment)
    {
        if (! $payment->tontine->isManagedBy(auth()->user())) {
            return response()->json(['message' => 'Seul le bureau de la caisse peut annuler un paiement.'], 403);
        }
        \App\Models\PaymentDeclaration::where('payment_id', $payment->id)
            ->update([
                'status' => 'rejected',
                'decision_note' => 'Paiement annulé par le bureau',
                'decided_by' => auth()->id(),
                'decided_at' => now(),
            ]);
        $payment->delete();

        return response()->json(['message' => 'Paiement annulé']);
    }
}
