<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Invitation;
use App\Models\JoinRequest;
use App\Models\Tontine;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rejoindre par QR code. Le bureau affiche l'invitation de son groupe ou de sa
 * caisse ; qui la scanne demande à entrer ; le bureau accepte ou refuse.
 * Personne n'entre seul : un QR peut circuler bien au-delà du groupe.
 */
class InvitationController extends Controller
{
    /** L'invitation en cours du groupe (créée au premier appel). */
    public function forGroup(Request $request, Group $group): JsonResponse
    {
        if (! $group->isManagedBy($request->user())) {
            return $this->forbidden();
        }

        return $this->present($this->activeFor('group_id', $group->id, $request->user()));
    }

    /** L'invitation en cours d'une caisse hors groupe. */
    public function forTontine(Request $request, Tontine $tontine): JsonResponse
    {
        if (! $tontine->isManagedBy($request->user())) {
            return $this->forbidden();
        }
        if ($tontine->group_id !== null) {
            return response()->json(['message' => 'Cette caisse fait partie d\'un groupe : invitez au groupe.'], 422);
        }

        return $this->present($this->activeFor('tontine_id', $tontine->id, $request->user()));
    }

    /** Nouveau lien : l'ancien QR ne marche plus. */
    public function renew(Request $request, string $token): JsonResponse
    {
        $old = Invitation::where('token', $token)->firstOrFail();
        if (! $old->isManagedBy($request->user())) {
            return $this->forbidden();
        }
        $old->update(['active' => false]);

        $key = $old->group_id ? 'group_id' : 'tontine_id';

        return $this->present($this->activeFor($key, $old->{$key}, $request->user()));
    }

    /** Ce que montre l'app avant de rejoindre : « Rejoindre … ? ». */
    public function show(Request $request, string $token): JsonResponse
    {
        $invitation = $this->valid($token);
        if ($invitation === null) {
            return $this->expired();
        }
        $user = $request->user();

        return response()->json(['data' => [
            ...$this->describe($invitation),
            'already_member' => $this->isMember($invitation, $user),
            'pending' => JoinRequest::where('invitation_id', $invitation->id)
                ->where('user_id', $user->id)
                ->where('status', JoinRequest::PENDING)
                ->exists(),
        ]]);
    }

    /** « Je veux rejoindre » : la demande attend le bureau. */
    public function join(Request $request, string $token): JsonResponse
    {
        $invitation = $this->valid($token);
        if ($invitation === null) {
            return $this->expired();
        }
        $user = $request->user();
        if ($this->isMember($invitation, $user)) {
            return response()->json(['message' => 'Vous êtes déjà membre.'], 409);
        }

        $joinRequest = JoinRequest::firstOrCreate(
            ['invitation_id' => $invitation->id, 'user_id' => $user->id, 'status' => JoinRequest::PENDING],
            ['name' => $user->name, 'phone' => $user->phone],
        );

        return response()->json(['data' => $joinRequest], 201);
    }

    /** Les demandes d'adhésion à traiter, pour le bureau. */
    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();
        $requests = JoinRequest::query()
            ->where('status', JoinRequest::PENDING)
            ->with('invitation.group', 'invitation.tontine')
            ->oldest()
            ->get()
            ->filter(fn (JoinRequest $r) => (int) $r->user_id !== (int) $user->id && $r->invitation?->isManagedBy($user))
            ->map(fn (JoinRequest $r) => [
                ...$r->only(['id', 'name', 'phone', 'status', 'created_at']),
                'target' => $this->describe($r->invitation),
            ])
            ->values();

        return response()->json(['data' => $requests]);
    }

    /**
     * Accepter : la personne devient membre — du groupe et de toutes ses
     * caisses, ou de la caisse.
     */
    public function approve(Request $request, JoinRequest $joinRequest): JsonResponse
    {
        if ($error = $this->guardDecision($request->user(), $joinRequest)) {
            return $error;
        }
        $invitation = $joinRequest->invitation;

        if ($invitation->group !== null) {
            $member = GroupMember::create([
                'group_id' => $invitation->group_id,
                'name' => $joinRequest->name,
                'phone' => $joinRequest->phone,
                'role' => 'membre',
            ]);
            foreach ($invitation->group->caisses as $caisse) {
                $invitation->group->enroll($member, $caisse);
            }
        } else {
            $tontine = $invitation->tontine;
            $tontine->members()->create([
                'name' => $joinRequest->name,
                'phone' => $joinRequest->phone,
                'role' => 'membre',
                'order_position' => (int) $tontine->members()->max('order_position') + 1,
            ]);
        }

        $joinRequest->update([
            'status' => JoinRequest::APPROVED,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return response()->json(['data' => $joinRequest->fresh()]);
    }

    public function reject(Request $request, JoinRequest $joinRequest): JsonResponse
    {
        if ($error = $this->guardDecision($request->user(), $joinRequest)) {
            return $error;
        }
        $joinRequest->update([
            'status' => JoinRequest::REJECTED,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return response()->json(['data' => $joinRequest->fresh()]);
    }

    private function activeFor(string $key, int $id, User $user): Invitation
    {
        return Invitation::where($key, $id)->where('active', true)->first()
            ?? Invitation::create([$key => $id, 'token' => Invitation::newToken(), 'created_by' => $user->id]);
    }

    private function valid(string $token): ?Invitation
    {
        $invitation = Invitation::where('token', $token)->where('active', true)->first();

        return $invitation?->target() === null ? null : $invitation;
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Invitation $invitation): array
    {
        $target = $invitation->target();

        return [
            'token' => $invitation->token,
            'url' => $invitation->url(),
            'kind' => $invitation->group_id ? 'group' : 'caisse',
            'name' => $target?->name,
            'members_count' => $target?->members()->count() ?? 0,
        ];
    }

    private function present(Invitation $invitation): JsonResponse
    {
        return response()->json(['data' => $this->describe($invitation)]);
    }

    private function isMember(Invitation $invitation, User $user): bool
    {
        $target = $invitation->target();

        return $target !== null && $target->members()
            ->where('phone', 'like', '%'.Phone::tail($user->phone))
            ->get()
            ->contains(fn ($m) => Phone::same($m->phone, $user->phone));
    }

    private function guardDecision(User $user, JoinRequest $joinRequest): ?JsonResponse
    {
        if ((int) $joinRequest->user_id === (int) $user->id || ! $joinRequest->invitation?->isManagedBy($user)) {
            return $this->forbidden();
        }
        if ($joinRequest->status !== JoinRequest::PENDING) {
            return response()->json(['message' => 'Cette demande a déjà été traitée.'], 400);
        }

        return null;
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['message' => 'Seul le bureau peut faire cela.'], 403);
    }

    private function expired(): JsonResponse
    {
        return response()->json(['message' => 'Ce lien d\'invitation n\'est plus valable. Demandez-en un nouveau au bureau.'], 404);
    }
}
