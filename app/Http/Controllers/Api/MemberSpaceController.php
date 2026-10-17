<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeclarationRequest;
use App\Models\Payment;
use App\Models\PaymentDeclaration;
use App\Models\Tontine;
use App\Models\TontineMember;
use App\Models\User;
use App\Services\GroupStatement;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * L'espace des membres : ils n'ont pas créé les caisses, mais y sont inscrits à
 * leur numéro. Ils y suivent leur situation et déclarent leurs paiements, que
 * le bureau valide.
 *
 * Un membre ne voit que ce qui le concerne : jamais les paiements des autres.
 */
class MemberSpaceController extends Controller
{
    public function __construct(private readonly GroupStatement $statement) {}

    /** Mes inscriptions : chaque caisse, ma situation, mes paiements, mes déclarations. */
    public function caisses(Request $request): JsonResponse
    {
        $user = $request->user();
        $memberships = $this->membershipsOf($user);
        $caisses = Tontine::query()
            ->whereIn('id', $memberships->pluck('tontine_id'))
            ->with(['cycles', 'payments', 'group'])
            ->get()
            ->keyBy('id');
        $declarations = PaymentDeclaration::query()
            ->whereIn('tontine_member_id', $memberships->pluck('id'))
            ->latest()
            ->get()
            ->groupBy('tontine_member_id');

        $data = $memberships
            ->filter(fn (TontineMember $m) => $caisses->has($m->tontine_id))
            ->map(function (TontineMember $m) use ($caisses, $declarations) {
                $caisse = $caisses[$m->tontine_id];
                $active = $caisse->cycles->where('status', 'active')->sortByDesc('cycle_number')->first();

                return [
                    'caisse' => [
                        'id' => $caisse->id,
                        'name' => $caisse->name,
                        'type' => $caisse->type,
                        'amount' => $caisse->amount,
                        'currency' => $caisse->currency ?? 'XOF',
                        'frequency' => $caisse->frequency,
                        'period_count' => $caisse->period_count,
                        'period_unit' => $caisse->period_unit,
                        'target_amount' => $caisse->target_amount,
                        'deadline' => $caisse->deadline?->toDateString(),
                        'group_name' => $caisse->group?->name,
                        'current_cycle' => $active,
                    ],
                    'member' => $m->only(['id', 'name', 'role', 'status']),
                    'standing' => $this->statement->line($caisse, $m),
                    'payments' => $caisse->payments
                        ->where('tontine_member_id', $m->id)
                        ->sortByDesc('paid_at')
                        ->values(),
                    'declarations' => $declarations->get($m->id, collect())->values(),
                ];
            })
            ->values();

        return response()->json(['data' => $data]);
    }

    /** « J'ai payé » : la déclaration attend la validation du bureau. */
    public function declare(StoreDeclarationRequest $request, Tontine $tontine): JsonResponse
    {
        $member = $this->membershipsOf($request->user())->firstWhere('tontine_id', $tontine->id);
        if ($member === null) {
            return response()->json(['message' => 'Vous n\'êtes pas inscrit dans cette caisse.'], 403);
        }

        $declaration = PaymentDeclaration::create([
            'tontine_id' => $tontine->id,
            'tontine_member_id' => $member->id,
            'cycle_id' => $request->input('cycle_id')
                ?? $tontine->cycles()->where('status', 'active')->orderByDesc('cycle_number')->value('id'),
            'user_id' => $request->user()->id,
            'amount' => $request->integer('amount'),
            'method' => $request->input('method'),
            'paid_at' => $request->input('paid_at') ?? now()->toDateString(),
            'reference' => $request->input('reference'),
            'proof_path' => $request->file('proof')?->store('declarations', 'local'),
            'status' => PaymentDeclaration::PENDING,
        ]);

        return response()->json(['data' => $declaration], 201);
    }

    /** Les déclarations à valider dans les caisses que je gère. */
    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();
        $declarations = PaymentDeclaration::query()
            ->where('status', PaymentDeclaration::PENDING)
            ->with(['tontine', 'member', 'cycle'])
            ->oldest()
            ->get()
            ->filter(fn (PaymentDeclaration $d) => $d->tontine?->isManagedBy($user))
            ->map(fn (PaymentDeclaration $d) => [
                ...$d->toArray(),
                'tontine' => $d->tontine->only(['id', 'name', 'type', 'currency']),
            ])
            ->values();

        return response()->json(['data' => $declarations]);
    }

    public function approve(Request $request, PaymentDeclaration $declaration): JsonResponse
    {
        if ($error = $this->guardDecision($request->user(), $declaration)) {
            return $error;
        }

        $payment = Payment::create([
            'tontine_id' => $declaration->tontine_id,
            'tontine_member_id' => $declaration->tontine_member_id,
            'cycle_id' => $declaration->cycle_id,
            'amount' => $declaration->amount,
            'method' => $declaration->method,
            'validated_by' => $request->user()->id,
            'paid_at' => $declaration->paid_at->copy()->setTimeFrom(now()),
        ]);

        $declaration->update([
            'status' => PaymentDeclaration::APPROVED,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'payment_id' => $payment->id,
        ]);

        return response()->json(['data' => $declaration->fresh()]);
    }

    public function reject(Request $request, PaymentDeclaration $declaration): JsonResponse
    {
        $request->validate(
            ['reason' => 'required|string|max:255'],
            ['reason.required' => 'Dites au membre pourquoi.'],
        );
        if ($error = $this->guardDecision($request->user(), $declaration)) {
            return $error;
        }

        $declaration->update([
            'status' => PaymentDeclaration::REJECTED,
            'decision_note' => $request->string('reason')->toString(),
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return response()->json(['data' => $declaration->fresh()]);
    }

    /** La photo du reçu : pour le bureau de la caisse et pour qui l'a déclarée. */
    public function proof(Request $request, PaymentDeclaration $declaration): StreamedResponse|JsonResponse
    {
        $user = $request->user();
        $allowed = (int) $declaration->user_id === (int) $user->id || $declaration->tontine?->isManagedBy($user);
        if (! $allowed || $declaration->proof_path === null) {
            return response()->json(['message' => 'Introuvable.'], 404);
        }

        return Storage::disk('local')->response($declaration->proof_path);
    }

    private function guardDecision(User $user, PaymentDeclaration $declaration): ?JsonResponse
    {
        if (! $declaration->tontine?->isManagedBy($user)) {
            return response()->json(['message' => 'Seul le bureau de la caisse peut valider.'], 403);
        }
        if ($declaration->status !== PaymentDeclaration::PENDING) {
            return response()->json(['message' => 'Cette déclaration a déjà été traitée.'], 400);
        }

        return null;
    }

    /**
     * Mes inscriptions, reconnues à mon numéro quelle que soit son écriture.
     *
     * @return Collection<int, TontineMember>
     */
    private function membershipsOf(User $user): Collection
    {
        return TontineMember::query()
            ->where('phone', 'like', '%'.Phone::tail($user->phone))
            ->get()
            ->filter(fn (TontineMember $m) => Phone::same($m->phone, $user->phone))
            ->values();
    }
}
