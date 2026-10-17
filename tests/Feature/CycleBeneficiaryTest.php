<?php

use App\Models\Cycle;
use App\Models\Tontine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * @return array{User, Tontine, Cycle}
 */
function tontineWithCycle(): array
{
    $owner = User::factory()->create(['phone' => '70000001', 'pin_code' => '1234']);
    $tontine = Tontine::create(['name' => 'Marché', 'amount' => 5000, 'frequency' => 'weekly', 'created_by' => $owner->id]);
    $tontine->members()->create(['name' => 'Awa', 'phone' => '70000011', 'role' => 'membre', 'order_position' => 1]);
    $tontine->members()->create(['name' => 'Fanta', 'phone' => '70000012', 'role' => 'presidente', 'order_position' => 2]);
    $cycle = $tontine->cycles()->create(['cycle_number' => 1, 'status' => 'active']);

    return [$owner, $tontine, $cycle];
}

it('lets the creator choose the beneficiary of the current cycle', function () {
    [$owner, $tontine, $cycle] = tontineWithCycle();
    $fanta = $tontine->members()->where('name', 'Fanta')->first();
    Sanctum::actingAs($owner);

    $this->patchJson("/api/tontines/{$tontine->id}/cycles/{$cycle->id}/beneficiary", ['member_id' => $fanta->id])
        ->assertOk()
        ->assertJsonPath('cycle.beneficiary.name', 'Fanta');

    expect($cycle->refresh()->beneficiary_id)->toBe($fanta->id);
});

it('lets the presidente choose, recognised by her phone', function () {
    [, $tontine, $cycle] = tontineWithCycle();
    $presidente = User::factory()->create(['phone' => '70000012', 'pin_code' => '0000']);
    $awa = $tontine->members()->where('name', 'Awa')->first();
    Sanctum::actingAs($presidente);

    $this->patchJson("/api/tontines/{$tontine->id}/cycles/{$cycle->id}/beneficiary", ['member_id' => $awa->id])
        ->assertOk();
});

it('refuses anyone else', function () {
    [, $tontine, $cycle] = tontineWithCycle();
    Sanctum::actingAs(User::factory()->create(['phone' => '79999999', 'pin_code' => '0000']));

    $this->patchJson("/api/tontines/{$tontine->id}/cycles/{$cycle->id}/beneficiary", [
        'member_id' => $tontine->members()->first()->id,
    ])->assertForbidden();
});

it('refuses a member of another tontine', function () {
    [$owner, $tontine, $cycle] = tontineWithCycle();
    $other = Tontine::create(['name' => 'Autre', 'amount' => 1000, 'frequency' => 'monthly', 'created_by' => $owner->id]);
    $stranger = $other->members()->create(['name' => 'Moussa', 'phone' => '70000099', 'role' => 'membre', 'order_position' => 1]);
    Sanctum::actingAs($owner);

    $this->patchJson("/api/tontines/{$tontine->id}/cycles/{$cycle->id}/beneficiary", ['member_id' => $stranger->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('member_id');
});

it('refuses a closed cycle', function () {
    [$owner, $tontine, $cycle] = tontineWithCycle();
    $cycle->update(['status' => 'completed']);
    Sanctum::actingAs($owner);

    $this->patchJson("/api/tontines/{$tontine->id}/cycles/{$cycle->id}/beneficiary", [
        'member_id' => $tontine->members()->first()->id,
    ])->assertStatus(400);
});
