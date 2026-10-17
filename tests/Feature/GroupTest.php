<?php

use App\Models\Group;
use App\Models\Tontine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create(['phone' => '70000001', 'pin_code' => '1234']);
    Sanctum::actingAs($this->owner);
});

/** Le village avec Moussa et Awa, et ses caisses. */
function village(): Group
{
    $id = test()->postJson('/api/groups', ['name' => 'Ressortissants de Kalaban'])->assertCreated()->json('data.id');
    test()->postJson("/api/groups/{$id}/members", ['name' => 'Moussa', 'phone' => '70000011'])->assertCreated();
    test()->postJson("/api/groups/{$id}/members", ['name' => 'Awa', 'phone' => '70000012', 'role' => 'tresoriere'])->assertCreated();

    return Group::find($id);
}

it('enrolls the group members in every new caisse', function () {
    $group = village();

    $id = $this->postJson('/api/tontines', [
        'name' => 'Caisse décès', 'type' => 'periodic', 'amount' => 2000, 'frequency' => 'monthly', 'group_id' => $group->id,
    ])->assertCreated()->assertJsonPath('data.group_id', $group->id)->json('data.id');

    expect(Tontine::find($id)->members()->pluck('name')->all())->toBe(['Moussa', 'Awa']);
});

it('adds a new group member to every existing caisse', function () {
    $group = village();
    $id = $this->postJson('/api/tontines', [
        'name' => 'Mosquée', 'type' => 'fundraising', 'target_amount' => 1000000, 'group_id' => $group->id,
    ])->json('data.id');

    $this->postJson("/api/groups/{$group->id}/members", ['name' => 'Bakary', 'phone' => '70000013'])->assertCreated();

    expect(Tontine::find($id)->members()->pluck('name')->all())->toContain('Bakary');
});

it('corrects a member once, in all caisses', function () {
    $group = village();
    $this->postJson('/api/tontines', ['name' => 'Décès', 'type' => 'periodic', 'amount' => 1000, 'frequency' => 'monthly', 'group_id' => $group->id]);
    $moussa = $group->members()->where('name', 'Moussa')->first();

    $this->patchJson("/api/groups/{$group->id}/members/{$moussa->id}", ['phone' => '76543210'])->assertOk();

    expect($moussa->enrollments()->first()->phone)->toBe('76543210');
});

it('tells each member where he stands in every caisse', function () {
    $group = village();
    $deces = $this->postJson('/api/tontines', [
        'name' => 'Décès', 'type' => 'periodic', 'amount' => 2000, 'frequency' => 'monthly', 'group_id' => $group->id,
    ])->json('data.id');
    $mosquee = $this->postJson('/api/tontines', [
        'name' => 'Mosquée', 'type' => 'fundraising', 'target_amount' => 1000000, 'group_id' => $group->id,
    ])->json('data.id');
    $decesCaisse = Tontine::find($deces);
    $moussaInDeces = $decesCaisse->members()->where('name', 'Moussa')->first();
    $cycle = $decesCaisse->cycles()->first();

    // Moussa paie le mois en cours de la caisse décès et donne 10 000 F pour la mosquée.
    $this->postJson('/api/payments', ['tontine_id' => $deces, 'tontine_member_id' => $moussaInDeces->id, 'cycle_id' => $cycle->id, 'amount' => 2000, 'method' => 'cash']);
    $moussaInMosquee = Tontine::find($mosquee)->members()->where('name', 'Moussa')->first();
    $this->postJson('/api/payments', ['tontine_id' => $mosquee, 'tontine_member_id' => $moussaInMosquee->id, 'amount' => 10000, 'method' => 'orange']);

    // Le mois se clôt : Awa n'a pas payé, elle est en retard.
    $this->postJson("/api/tontines/{$deces}/cycles/{$cycle->id}/close")->assertOk();

    $statement = collect($this->getJson("/api/groups/{$group->id}/statement")->assertOk()->json('data'))
        ->keyBy('member.name');

    expect($statement['Moussa']['paid_total'])->toBe(12000)
        ->and($statement['Moussa']['late_amount'])->toBe(0)
        ->and($statement['Moussa']['current_due'])->toBe(2000)
        ->and($statement['Moussa']['status'])->toBe('a_payer')
        ->and($statement['Awa']['late_amount'])->toBe(2000)
        ->and($statement['Awa']['status'])->toBe('en_retard');

    $mosqueeLine = collect($statement['Moussa']['caisses'])->firstWhere('name', 'Mosquée');
    expect($mosqueeLine['paid_total'])->toBe(10000)->and($mosqueeLine['status'])->toBe('a_jour');
});

it('lets the bureau manage the group, nobody else', function () {
    $group = village();

    Sanctum::actingAs(User::factory()->create(['phone' => '79999999', 'pin_code' => '0000']));
    $this->postJson("/api/groups/{$group->id}/members", ['name' => 'Intrus'])->assertForbidden();
    $this->postJson('/api/tontines', ['name' => 'X', 'amount' => 1, 'frequency' => 'monthly', 'group_id' => $group->id])->assertForbidden();

    // Awa est trésorière : reconnue à son téléphone.
    Sanctum::actingAs(User::factory()->create(['phone' => '70000012', 'pin_code' => '0000']));
    $this->postJson("/api/groups/{$group->id}/members", ['name' => 'Fanta'])->assertCreated();
});

it('pauses the enrollments of a member removed from the group', function () {
    $group = village();
    $id = $this->postJson('/api/tontines', ['name' => 'Décès', 'type' => 'periodic', 'amount' => 1000, 'frequency' => 'monthly', 'group_id' => $group->id])->json('data.id');
    $moussa = $group->members()->where('name', 'Moussa')->first();

    $this->deleteJson("/api/groups/{$group->id}/members/{$moussa->id}")->assertOk();

    expect(Tontine::find($id)->members()->where('name', 'Moussa')->first()->status)->toBe('paused');
});

it('lists my groups with their counts', function () {
    village();

    $this->getJson('/api/groups')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Ressortissants de Kalaban')
        ->assertJsonPath('data.0.members_count', 2);
});

it('counts every period since the start of a backdated caisse', function () {
    $group = village();
    $id = $this->postJson('/api/tontines', [
        'name' => 'Décès', 'type' => 'periodic', 'amount' => 2000, 'frequency' => 'monthly',
        'group_id' => $group->id, 'start_date' => now()->subMonths(2)->toDateString(),
    ])->json('data.id');

    $statement = collect($this->getJson("/api/groups/{$group->id}/statement")->json('data'))->keyBy('member.name');

    // Deux mois passés, clos d'office à la création, et le mois en cours.
    expect($statement['Moussa']['late_amount'])->toBe(4000)
        ->and($statement['Moussa']['current_due'])->toBe(2000)
        ->and($statement['Moussa']['status'])->toBe('en_retard');
});

it('does not charge a newcomer for periods closed before he joined', function () {
    $group = village();
    $id = $this->postJson('/api/tontines', [
        'name' => 'Décès', 'type' => 'periodic', 'amount' => 2000, 'frequency' => 'monthly',
        'group_id' => $group->id, 'start_date' => now()->subMonths(2)->toDateString(),
    ])->json('data.id');

    $this->travel(2)->days();
    $this->postJson("/api/groups/{$group->id}/members", ['name' => 'Bakary', 'phone' => '70000013'])->assertCreated();

    $statement = collect($this->getJson("/api/groups/{$group->id}/statement")->json('data'))->keyBy('member.name');

    expect($statement['Bakary']['late_amount'])->toBe(0)
        ->and($statement['Bakary']['current_due'])->toBe(2000);
});

it('tracks a fixed share paid little by little (château d\'eau)', function () {
    $group = village();
    $id = $this->postJson('/api/tontines', [
        'name' => 'Château d\'eau', 'type' => 'share', 'amount' => 200000,
        'deadline' => now()->addMonths(6)->toDateString(), 'group_id' => $group->id,
    ])->assertCreated()->assertJsonPath('data.type', 'share')->json('data.id');
    $moussa = Tontine::find($id)->members()->where('name', 'Moussa')->first();

    foreach ([50000, 30000] as $amount) {
        $this->postJson('/api/payments', ['tontine_id' => $id, 'tontine_member_id' => $moussa->id, 'amount' => $amount, 'method' => 'cash'])
            ->assertSuccessful();
    }

    $statement = collect($this->getJson("/api/groups/{$group->id}/statement")->json('data'))->keyBy('member.name');
    $line = collect($statement['Moussa']['caisses'])->firstWhere('name', 'Château d\'eau');

    expect($line['paid_total'])->toBe(80000)
        ->and($line['current_due'])->toBe(120000)
        ->and($line['status'])->toBe('a_payer');

    // Date limite passée : le reste devient un retard.
    $this->travel(7)->months();
    $line = collect(collect($this->getJson("/api/groups/{$group->id}/statement")->json('data'))
        ->keyBy('member.name')['Moussa']['caisses'])->firstWhere('name', 'Château d\'eau');
    expect($line['late_amount'])->toBe(120000)->and($line['status'])->toBe('en_retard');
});

it('needs the share amount, not a frequency, for a fixed share', function () {
    $this->postJson('/api/tontines', ['name' => 'Château', 'type' => 'share'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amount')
        ->assertJsonMissingValidationErrors('frequency');
});

it('gives the group currency to its caisses, unless one has its own', function () {
    $id = $this->postJson('/api/groups', ['name' => 'Section France', 'currency' => 'EUR'])
        ->assertCreated()->assertJsonPath('data.currency', 'EUR')->json('data.id');

    $this->postJson('/api/tontines', ['name' => 'Décès', 'type' => 'periodic', 'amount' => 20, 'frequency' => 'monthly', 'group_id' => $id])
        ->assertJsonPath('data.currency', 'EUR');
    $this->postJson('/api/tontines', ['name' => 'Section USA', 'type' => 'periodic', 'amount' => 25, 'frequency' => 'monthly', 'group_id' => $id, 'currency' => 'USD'])
        ->assertJsonPath('data.currency', 'USD');
    $this->postJson('/api/tontines', ['name' => 'Au pays', 'amount' => 5000, 'frequency' => 'monthly'])
        ->assertJsonPath('data.currency', 'XOF');
    $this->postJson('/api/tontines', ['name' => 'X', 'amount' => 1, 'frequency' => 'monthly', 'currency' => 'BTC'])
        ->assertJsonValidationErrors('currency');
});
