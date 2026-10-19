<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddMemberRequest;
use App\Models\Tontine;
use App\Models\TontineMember;
use Illuminate\Http\Request;

class TontineMemberController extends Controller
{
    public function index(Tontine $tontine)
    {
        // Afficher seulement les membres actifs (si colonne status existe)
        $query = $tontine->members();

        // Vérifier si colonne 'status' existe
        if (\Schema::hasColumn('tontine_members', 'status')) {
            $query->where('status', 'active');
        }

        return $query->orderBy('order_position')->get();
    }

    public function paused(Tontine $tontine)
    {
        // Afficher seulement les membres en pause (si colonne status existe)
        $query = $tontine->members();

        // Vérifier si colonne 'status' existe
        if (\Schema::hasColumn('tontine_members', 'status')) {
            $query->where('status', 'paused');
        } else {
            return [];
        }

        return $query->orderBy('order_position')->get();
    }

    public function store(AddMemberRequest $request, Tontine $tontine)
    {
        if (! $tontine->isManagedBy($request->user())) {
            return $this->forbidden();
        }

        return $tontine->members()->create($request->validated());
    }

    public function update(Request $request, Tontine $tontine, TontineMember $member)
    {
        // Vérifier que le membre appartient à la tontine
        if ((int) $member->tontine_id !== (int) $tontine->id) {
            return response()->json(['error' => 'Not found'], 404);
        }
        if (! $tontine->isManagedBy(auth()->user())) {
            return $this->forbidden();
        }

        $request->validate([
            'name' => 'sometimes|required|string',
            'phone' => 'sometimes|required|string',
            'role' => 'sometimes|required|in:presidente,tresoriere,membre',
        ]);

        $member->update($request->only('name', 'phone', 'role'));

        return $member;
    }

    public function destroy(Tontine $tontine, TontineMember $member)
    {
        // Vérifier que le membre appartient à la tontine
        if ((int) $member->tontine_id !== (int) $tontine->id) {
            return response()->json(['error' => 'Not found'], 404);
        }
        if (! $tontine->isManagedBy(auth()->user())) {
            return $this->forbidden();
        }

        // Ses paiements partiraient avec lui : les comptes de la caisse seraient faux.
        if ($member->payments()->exists()) {
            return response()->json(['message' => 'Ce membre a déjà payé : mettez-le en pause, ses paiements restent dans les comptes.'], 422);
        }
        \App\Models\Cycle::where('beneficiary_id', $member->id)->update(['beneficiary_id' => null]);
        $member->delete();

        return response()->json(['message' => 'Membre supprimé avec succès'], 200);
    }

    public function pause(Tontine $tontine, TontineMember $member)
    {
        // Vérifier que le membre appartient à la tontine
        if ((int) $member->tontine_id !== (int) $tontine->id) {
            return response()->json(['error' => 'Not found'], 404);
        }
        if (! $tontine->isManagedBy(auth()->user())) {
            return $this->forbidden();
        }

        // Marquer comme en pause au lieu de supprimer
        $member->update(['status' => 'paused']);

        return response()->json(['message' => 'Membre mis en pause'], 200);
    }

    public function resume(Tontine $tontine, TontineMember $member)
    {
        // Vérifier que le membre appartient à la tontine
        if ((int) $member->tontine_id !== (int) $tontine->id) {
            return response()->json(['error' => 'Not found'], 404);
        }
        if (! $tontine->isManagedBy(auth()->user())) {
            return $this->forbidden();
        }

        // Réactiver le membre
        $member->update(['status' => 'active']);

        return response()->json(['message' => 'Membre réactivé'], 200);
    }

    private function forbidden()
    {
        return response()->json(['message' => 'Seul le bureau de la caisse peut gérer ses membres.'], 403);
    }
}
