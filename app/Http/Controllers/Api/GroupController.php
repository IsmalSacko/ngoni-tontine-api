<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGroupMemberRequest;
use App\Http\Resources\TontineResource;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Tontine;
use App\Services\GroupStatement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Groupes (associations) : leurs membres, saisis une fois, et leurs caisses.
 */
class GroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $groups = Group::query()
            ->where('created_by', $request->user()->id)
            ->withCount(['members', 'caisses'])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $groups]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'currency' => 'nullable|in:'.implode(',', Tontine::CURRENCIES),
        ], ['name.required' => 'Donnez un nom au groupe.']);
        $data['currency'] ??= 'XOF';

        $group = Group::create([...$data, 'created_by' => $request->user()->id]);

        return response()->json(['data' => $group->loadCount(['members', 'caisses'])], 201);
    }

    public function show(Group $group): JsonResponse
    {
        $caisses = $group->caisses()
            ->with(['members', 'cycles'])
            ->withSum('payments', 'amount')
            ->withSum('expenses', 'amount')
            ->get();

        return response()->json([
            'data' => [
                ...$group->loadCount(['members', 'caisses'])->toArray(),
                'members' => $group->members()->orderBy('name')->get(),
                'caisses' => TontineResource::collection($caisses)->resolve(),
            ],
        ]);
    }

    public function update(Request $request, Group $group): JsonResponse
    {
        if (! $group->isManagedBy($request->user())) {
            return $this->forbidden();
        }

        $group->update($request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'currency' => 'sometimes|in:'.implode(',', Tontine::CURRENCIES),
        ]));

        return response()->json(['data' => $group->loadCount(['members', 'caisses'])]);
    }

    public function statement(Group $group, GroupStatement $statement): JsonResponse
    {
        return response()->json(['data' => $statement->forGroup($group)]);
    }

    // ---------------------------------------------------------------- Membres

    /**
     * Ajoute un membre au groupe et, par défaut, l'inscrit dans toutes ses caisses.
     */
    public function storeMember(StoreGroupMemberRequest $request, Group $group): JsonResponse
    {
        if (! $group->isManagedBy($request->user())) {
            return $this->forbidden();
        }

        $member = $group->members()->create([
            'name' => $request->string('name')->trim()->toString(),
            'phone' => $request->input('phone'),
            'role' => $request->input('role', 'membre'),
        ]);

        if ($request->boolean('add_to_caisses', true)) {
            foreach ($group->caisses as $caisse) {
                $group->enroll($member, $caisse);
            }
        }

        return response()->json(['data' => $member], 201);
    }

    /**
     * Modifie un membre, et ses inscriptions dans les caisses avec lui : le nom
     * et le téléphone ne se corrigent qu'une fois.
     */
    public function updateMember(StoreGroupMemberRequest $request, Group $group, GroupMember $member): JsonResponse
    {
        if ((int) $member->group_id !== (int) $group->id) {
            return response()->json(['message' => 'Membre introuvable dans ce groupe'], 404);
        }
        if (! $group->isManagedBy($request->user())) {
            return $this->forbidden();
        }

        $member->update($request->only('name', 'phone', 'role'));
        $member->enrollments()->update($request->only('name', 'phone', 'role'));

        return response()->json(['data' => $member]);
    }

    /**
     * Retire un membre du groupe. Ses inscriptions passent en pause : ses
     * cotisations passées restent dans l'historique des caisses.
     */
    public function destroyMember(Request $request, Group $group, GroupMember $member): JsonResponse
    {
        if ((int) $member->group_id !== (int) $group->id) {
            return response()->json(['message' => 'Membre introuvable dans ce groupe'], 404);
        }
        if (! $group->isManagedBy($request->user())) {
            return $this->forbidden();
        }

        $member->enrollments()->update(['status' => 'paused']);
        $member->delete();

        return response()->json(['message' => 'Membre retiré du groupe']);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['message' => 'Seul le bureau du groupe peut faire cela.'], 403);
    }
}
