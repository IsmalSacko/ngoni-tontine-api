<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\Expense;
use App\Models\Tontine;
use Illuminate\Http\JsonResponse;

/**
 * Sorties d'argent d'une caisse commune.
 *
 * Tout le monde voit les dépenses — c'est la transparence qui fait tenir une
 * caisse d'association ; seul le bureau (créatrice, présidente, trésorière) en
 * enregistre ou en retire.
 */
class ExpenseController extends Controller
{
    public function index(Tontine $tontine): JsonResponse
    {
        return response()->json([
            'data' => $tontine->expenses()->latest('spent_at')->latest('id')->get(),
        ]);
    }

    public function store(StoreExpenseRequest $request, Tontine $tontine): JsonResponse
    {
        if (! $tontine->isManagedBy($request->user())) {
            return response()->json(['message' => 'Seul le bureau de la caisse peut enregistrer une dépense.'], 403);
        }

        $expense = $tontine->expenses()->create([
            'amount' => $request->integer('amount'),
            'label' => $request->string('label')->trim()->toString(),
            'note' => $request->input('note'),
            'spent_at' => $request->input('spent_at') ?? now()->toDateString(),
            'recorded_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $expense], 201);
    }

    public function destroy(Tontine $tontine, Expense $expense): JsonResponse
    {
        if ((int) $expense->tontine_id !== (int) $tontine->id) {
            return response()->json(['message' => 'Dépense introuvable pour cette caisse'], 404);
        }

        if (! $tontine->isManagedBy(auth()->user())) {
            return response()->json(['message' => 'Seul le bureau de la caisse peut retirer une dépense.'], 403);
        }

        $expense->delete();

        return response()->json(['message' => 'Dépense supprimée']);
    }
}
