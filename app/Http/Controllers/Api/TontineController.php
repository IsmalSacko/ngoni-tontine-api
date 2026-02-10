<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTontineRequest;
use App\Http\Resources\TontineResource;
use App\Models\Tontine;
use Illuminate\Http\Request;

class TontineController extends Controller
{
    public function index()
    {
        $tontines = auth()->user()->tontines()->with(['members', 'cycles'])->get();
        return TontineResource::collection($tontines);
    }

    public function store(CreateTontineRequest $request)
    {
        $tontine = Tontine::create([
            ...$request->validated(),
            'created_by' => auth()->id(),
        ]);

        // Créer automatiquement le premier cycle
        $tontine->cycles()->create([
            'cycle_number' => 1,
            'beneficiary_id' => null, // Sera défini plus tard
        ]);

        // Charger les relations pour le resource
        $tontine->load('members', 'cycles');

        return new TontineResource($tontine);
    }

    public function show(Tontine $tontine)
    {
        $tontine->load(['members', 'cycles', 'payments']);
        return new TontineResource($tontine);
    }


    public function update(Request $request, Tontine $tontine)
    {
        $this->authorize('update', $tontine);
        $request->validate([
            'name' => 'sometimes|required|string',
            'amount' => 'sometimes|required|integer|min:1',
            'frequency' => 'sometimes|required|in:daily,weekly,biweekly,monthly,quarterly,yearly',
            'description' => 'sometimes|nullable|string',
        ]);

        $tontine->update($request->only('name', 'amount', 'frequency', 'description'));

        return new TontineResource($tontine);
    }

    public function destroy(Tontine $tontine)
    {
        $this->authorize('delete', $tontine);
        $tontine->delete();
        return response()->json(['message' => 'Tontine supprimée avec succès'], 200);
    }




}

