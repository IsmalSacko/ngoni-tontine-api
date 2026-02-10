<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Models\Tontine;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Tontine $tontine)
    {
        return $tontine->payments()->with('member')->orderBy('paid_at', 'desc')->get();
    }

    public function store (StorePaymentRequest $request){
        // Loguer les données reçues pour débogage
        \Log::info('Payment request received:', $request->all());
        
        $tontineId = $request->input('tontine_id');
        $cycleId = $request->input('cycle_id');
        
        // Récupérer ou créer le cycle pour cette tontine
        if (!$cycleId) {
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
            if (!$cycle) {
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
            'paid_at' => now(),
        ]);
        
        \Log::info('Payment created successfully:', ['payment_id' => $payment->id]);
        
        return $payment;
    }
}
