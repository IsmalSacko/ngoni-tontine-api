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
        return $tontine->members()->create($request->validated());
    }

    public function update(Request $request, Tontine $tontine, TontineMember $member)
    {
        // Vérifier que le membre appartient à la tontine
        if ($member->tontine_id !== $tontine->id) {
            return response()->json(['error' => 'Not found'], 404);
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
        if ($member->tontine_id !== $tontine->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $member->delete();

        return response()->json(['message' => 'Membre supprimé avec succès'], 200);
    }

    public function pause(Tontine $tontine, TontineMember $member)
    {
        // Vérifier que le membre appartient à la tontine
        if ($member->tontine_id !== $tontine->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // Marquer comme en pause au lieu de supprimer
        $member->update(['status' => 'paused']);

        return response()->json(['message' => 'Membre mis en pause'], 200);
    }

    public function resume(Tontine $tontine, TontineMember $member)
    {
        // Vérifier que le membre appartient à la tontine
        if ($member->tontine_id !== $tontine->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // Réactiver le membre
        $member->update(['status' => 'active']);

        return response()->json(['message' => 'Membre réactivé'], 200);
    }
}
