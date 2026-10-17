<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTontineRequest;
use App\Http\Resources\TontineResource;
use App\Models\Group;
use App\Models\Tontine;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TontineController extends Controller
{
    public function index()
    {
        $tontines = auth()->user()->tontines()
            ->with(['members', 'cycles'])
            ->withSum('payments', 'amount')
            ->withSum('expenses', 'amount')
            ->get();

        return TontineResource::collection($tontines);
    }

    public function store(CreateTontineRequest $request)
    {
        $group = $request->filled('group_id') ? Group::find($request->integer('group_id')) : null;
        if ($group !== null && ! $group->isManagedBy($request->user())) {
            return response()->json(['message' => 'Seul le bureau du groupe peut y créer une caisse.'], 403);
        }

        $tontine = Tontine::create([
            ...$request->validated(),
            'type' => $request->input('type', Tontine::TYPE_ROTATING),
            // Une collecte n'a ni cotisation fixe ni période : chacun donne ce
            // qu'il peut. Les colonnes restent remplies pour les anciennes apps.
            'amount' => $request->integer('amount'),
            'frequency' => $request->input('frequency') ?? Tontine::FREQUENCY_MONTHLY,
            'created_by' => auth()->id(),
        ]);

        $periodStart = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : Carbon::today();
        $periodEnd = $this->calculatePeriodEnd($periodStart, $tontine->frequency);

        // Créer automatiquement le premier cycle
        $tontine->cycles()->create([
            'cycle_number' => 1,
            'beneficiary_id' => null, // Sera défini plus tard
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
        ]);

        // Les membres du groupe sont inscrits d'office dans sa nouvelle caisse.
        if ($group !== null) {
            foreach ($group->members()->orderBy('id')->get() as $member) {
                $group->enroll($member, $tontine);
            }
        }

        // Charger les relations pour le resource
        $tontine->load('members', 'cycles')->loadSum('payments', 'amount')->loadSum('expenses', 'amount');

        return new TontineResource($tontine);
    }

    public function show(Tontine $tontine)
    {
        $tontine->load(['members', 'cycles', 'payments'])
            ->loadSum('payments', 'amount')
            ->loadSum('expenses', 'amount');

        return new TontineResource($tontine);
    }

    public function update(Request $request, Tontine $tontine)
    {
        $this->authorize('update', $tontine);
        $request->validate([
            'name' => 'sometimes|required|string',
            'amount' => 'sometimes|required|integer|min:0',
            'frequency' => 'sometimes|required|in:daily,weekly,biweekly,monthly,quarterly,yearly',
            'description' => 'sometimes|nullable|string',
            'target_amount' => 'sometimes|nullable|integer|min:1',
            'deadline' => 'sometimes|nullable|date',
        ]);

        $tontine->update($request->only('name', 'amount', 'frequency', 'description', 'target_amount', 'deadline'));
        $tontine->loadSum('payments', 'amount')->loadSum('expenses', 'amount');

        return new TontineResource($tontine);
    }

    public function destroy(Tontine $tontine)
    {
        $this->authorize('delete', $tontine);
        $tontine->delete();

        return response()->json(['message' => 'Tontine supprimée avec succès'], 200);
    }

    private function calculatePeriodEnd(Carbon $startDate, string $frequency): Carbon
    {
        return match ($frequency) {
            'daily' => $startDate->copy(),
            'weekly' => $startDate->copy()->addDays(6),
            'biweekly' => $startDate->copy()->addDays(13),
            'monthly' => $startDate->copy()->addMonth()->subDay(),
            'quarterly' => $startDate->copy()->addMonths(3)->subDay(),
            'yearly' => $startDate->copy()->addYear()->subDay(),
            default => $startDate->copy()->addMonth()->subDay(),
        };
    }
}
