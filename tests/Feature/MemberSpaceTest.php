<?php

use App\Models\PaymentDeclaration;
use App\Models\Tontine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->create(['phone' => '+33609090990', 'pin_code' => '1234']);
    $this->caisse = Tontine::create([
        'name' => 'Décès', 'type' => 'periodic', 'amount' => 20, 'frequency' => 'monthly',
        'currency' => 'EUR', 'created_by' => $this->owner->id,
    ]);
    $this->cycle = $this->caisse->cycles()->create(['cycle_number' => 1, 'status' => 'active']);
    // Moussa a été saisi par le bureau au format local, il a un compte international.
    $this->moussa = $this->caisse->members()->create(['name' => 'Moussa', 'phone' => '06 12 34 56 78', 'role' => 'membre', 'order_position' => 1]);
    $this->awa = $this->caisse->members()->create(['name' => 'Awa', 'phone' => '0687654321', 'role' => 'membre', 'order_position' => 2]);
    $this->moussaUser = User::factory()->create(['phone' => '+33612345678', 'pin_code' => '0000']);
});

it('shows a member his own situation, never the payments of others', function () {
    $this->caisse->payments()->create(['tontine_member_id' => $this->awa->id, 'cycle_id' => $this->cycle->id, 'amount' => 20, 'method' => 'cash', 'paid_at' => now(), 'validated_by' => $this->owner->id]);
    Sanctum::actingAs($this->moussaUser);

    $this->getJson('/api/me/caisses')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.caisse.name', 'Décès')
        ->assertJsonPath('data.0.caisse.currency', 'EUR')
        ->assertJsonPath('data.0.member.name', 'Moussa')
        ->assertJsonPath('data.0.standing.current_due', 20)
        ->assertJsonCount(0, 'data.0.payments');
});

it('lets a member declare a payment, with the photo of the receipt', function () {
    Sanctum::actingAs($this->moussaUser);

    $id = $this->post("/api/tontines/{$this->caisse->id}/declarations", [
        'amount' => 20, 'method' => 'wave', 'reference' => 'WV-123', 'paid_at' => now()->toDateString(),
        'proof' => UploadedFile::fake()->image('recu.jpg'),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.has_proof', true)
        ->assertJsonMissingPath('data.proof_path')
        ->json('data.id');

    expect(PaymentDeclaration::find($id)->cycle_id)->toBe($this->cycle->id);
    $this->getJson('/api/me/caisses')->assertJsonPath('data.0.declarations.0.status', 'pending');
});

it('refuses a declaration from someone who is not in the caisse', function () {
    Sanctum::actingAs(User::factory()->create(['phone' => '+33600000000', 'pin_code' => '0000']));

    $this->postJson("/api/tontines/{$this->caisse->id}/declarations", ['amount' => 20, 'method' => 'cash'])
        ->assertForbidden();
});

it('lets the bureau approve: the payment enters the caisse', function () {
    Sanctum::actingAs($this->moussaUser);
    $id = $this->postJson("/api/tontines/{$this->caisse->id}/declarations", ['amount' => 20, 'method' => 'orange'])->json('data.id');

    Sanctum::actingAs($this->owner);
    $this->getJson('/api/declarations/pending')->assertJsonCount(1, 'data')->assertJsonPath('data.0.member.name', 'Moussa');
    $this->postJson("/api/declarations/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    $this->postJson("/api/declarations/{$id}/approve")->assertStatus(400);

    expect($this->caisse->payments()->where('tontine_member_id', $this->moussa->id)->sum('amount'))->toBe(20);
    $this->getJson('/api/declarations/pending')->assertJsonCount(0, 'data');

    Sanctum::actingAs($this->moussaUser);
    $this->getJson('/api/me/caisses')
        ->assertJsonPath('data.0.standing.current_due', 0)
        ->assertJsonCount(1, 'data.0.payments');
});

it('lets the bureau reject with a reason the member reads', function () {
    Sanctum::actingAs($this->moussaUser);
    $id = $this->postJson("/api/tontines/{$this->caisse->id}/declarations", ['amount' => 20, 'method' => 'cash'])->json('data.id');

    Sanctum::actingAs($this->owner);
    $this->postJson("/api/declarations/{$id}/reject")->assertUnprocessable();
    $this->postJson("/api/declarations/{$id}/reject", ['reason' => 'Pas reçu sur Orange Money'])->assertOk();

    Sanctum::actingAs($this->moussaUser);
    $this->getJson('/api/me/caisses')
        ->assertJsonPath('data.0.declarations.0.status', 'rejected')
        ->assertJsonPath('data.0.declarations.0.decision_note', 'Pas reçu sur Orange Money');
});

it('lets nobody but the bureau decide', function () {
    Sanctum::actingAs($this->moussaUser);
    $id = $this->postJson("/api/tontines/{$this->caisse->id}/declarations", ['amount' => 20, 'method' => 'cash'])->json('data.id');

    $this->postJson("/api/declarations/{$id}/approve")->assertForbidden();
    $this->getJson('/api/declarations/pending')->assertJsonCount(0, 'data');
});
