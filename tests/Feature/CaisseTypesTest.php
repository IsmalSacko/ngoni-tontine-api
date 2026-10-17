<?php

use App\Models\Tontine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create(['phone' => '70000001', 'pin_code' => '1234']);
    Sanctum::actingAs($this->owner);
});

it('creates a periodic caisse (décès, rapatriement)', function () {
    $this->postJson('/api/tontines', [
        'name' => 'Caisse décès',
        'type' => 'periodic',
        'amount' => 2000,
        'frequency' => 'monthly',
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'periodic')
        ->assertJsonPath('data.total_collected', 0);
});

it('creates a fundraising without fixed amount, but with a target', function () {
    $this->postJson('/api/tontines', [
        'name' => 'Mosquée',
        'type' => 'fundraising',
        'target_amount' => 10000000,
        'deadline' => '2027-06-30',
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'fundraising')
        ->assertJsonPath('data.target_amount', 10000000)
        ->assertJsonPath('data.deadline', '2027-06-30');

    $this->postJson('/api/tontines', ['name' => 'Sans objectif', 'type' => 'fundraising'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('target_amount');
});

it('keeps old clients working: no type means a rotating tontine', function () {
    $this->postJson('/api/tontines', ['name' => 'Femmes', 'amount' => 5000, 'frequency' => 'weekly'])
        ->assertCreated()
        ->assertJsonPath('data.type', 'rotating');
});

it('records expenses and shows the balance', function () {
    $id = $this->postJson('/api/tontines', [
        'name' => 'Caisse décès', 'type' => 'periodic', 'amount' => 2000, 'frequency' => 'monthly',
    ])->json('data.id');
    $tontine = Tontine::find($id);
    $member = $tontine->members()->create(['name' => 'Moussa', 'phone' => '70000002', 'role' => 'membre', 'order_position' => 1]);

    $this->postJson('/api/payments', [
        'tontine_id' => $id, 'tontine_member_id' => $member->id, 'amount' => 50000, 'method' => 'cash',
    ])->assertSuccessful();

    $this->postJson("/api/tontines/{$id}/expenses", ['amount' => 30000, 'label' => 'Rapatriement de M. Keïta', 'spent_at' => '2026-10-12'])
        ->assertCreated()
        ->assertJsonPath('data.label', 'Rapatriement de M. Keïta');

    $this->getJson("/api/tontines/{$id}")
        ->assertJsonPath('data.total_collected', 50000)
        ->assertJsonPath('data.total_spent', 30000);

    $this->getJson("/api/tontines/{$id}/expenses")->assertJsonCount(1, 'data');
});

it('lets only the bureau record or remove an expense', function () {
    $id = $this->postJson('/api/tontines', [
        'name' => 'Caisse', 'type' => 'periodic', 'amount' => 1000, 'frequency' => 'monthly',
    ])->json('data.id');
    $tontine = Tontine::find($id);
    $tontine->members()->create(['name' => 'Awa', 'phone' => '70000003', 'role' => 'tresoriere', 'order_position' => 1]);
    $expense = $tontine->expenses()->create(['amount' => 1000, 'label' => 'Ciment', 'spent_at' => '2026-10-01']);

    Sanctum::actingAs(User::factory()->create(['phone' => '79999999', 'pin_code' => '0000']));
    $this->postJson("/api/tontines/{$id}/expenses", ['amount' => 1, 'label' => 'x'])->assertForbidden();
    $this->deleteJson("/api/tontines/{$id}/expenses/{$expense->id}")->assertForbidden();

    Sanctum::actingAs(User::factory()->create(['phone' => '70000003', 'pin_code' => '0000']));
    $this->deleteJson("/api/tontines/{$id}/expenses/{$expense->id}")->assertOk();
    expect($tontine->expenses()->count())->toBe(0);
});

it('closes a period of a periodic caisse even with late members, without beneficiary', function () {
    $id = $this->postJson('/api/tontines', [
        'name' => 'Caisse', 'type' => 'periodic', 'amount' => 1000, 'frequency' => 'monthly',
    ])->json('data.id');
    $tontine = Tontine::find($id);
    $tontine->members()->create(['name' => 'Awa', 'phone' => '70000003', 'role' => 'membre', 'order_position' => 1]);
    $cycle = $tontine->cycles()->first();

    $this->postJson("/api/tontines/{$id}/cycles/{$cycle->id}/close")
        ->assertOk()
        ->assertJsonPath('next_cycle.cycle_number', 2)
        ->assertJsonPath('next_cycle.beneficiary_id', null);
});

it('still waits for everyone before closing a rotating tontine', function () {
    $id = $this->postJson('/api/tontines', ['name' => 'Femmes', 'amount' => 5000, 'frequency' => 'weekly'])->json('data.id');
    $tontine = Tontine::find($id);
    $tontine->members()->create(['name' => 'Awa', 'phone' => '70000003', 'role' => 'membre', 'order_position' => 1]);
    $cycle = $tontine->cycles()->first();

    $this->postJson("/api/tontines/{$id}/cycles/{$cycle->id}/close")->assertStatus(400);
});

it('refuses a payment for a member of another caisse', function () {
    $a = $this->postJson('/api/tontines', ['name' => 'A', 'amount' => 1000, 'frequency' => 'monthly'])->json('data.id');
    $b = $this->postJson('/api/tontines', ['name' => 'B', 'amount' => 1000, 'frequency' => 'monthly'])->json('data.id');
    $stranger = Tontine::find($b)->members()->create(['name' => 'Awa', 'phone' => '70000003', 'role' => 'membre', 'order_position' => 1]);
    $otherCycle = Tontine::find($b)->cycles()->first();

    $this->postJson('/api/payments', ['tontine_id' => $a, 'tontine_member_id' => $stranger->id, 'amount' => 1000, 'method' => 'cash'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tontine_member_id');

    $member = Tontine::find($a)->members()->create(['name' => 'Moussa', 'phone' => '70000004', 'role' => 'membre', 'order_position' => 1]);
    $this->postJson('/api/payments', ['tontine_id' => $a, 'tontine_member_id' => $member->id, 'cycle_id' => $otherCycle->id, 'amount' => 1000, 'method' => 'cash'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cycle_id');
});
