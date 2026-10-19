<?php

use App\Models\Group;
use App\Models\Payment;
use App\Models\Tontine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create(['phone' => '70000001', 'pin_code' => '1234']);
    Sanctum::actingAs($this->owner);
});

/** Une caisse du type demandé avec Moussa inscrit. */
function caisse(array $fields): array
{
    $id = test()->postJson('/api/tontines', $fields)->assertCreated()->json('data.id');
    $member = Tontine::find($id)->members()->create(['name' => 'Moussa', 'phone' => '70000002', 'role' => 'membre', 'order_position' => 1]);

    return [$id, $member->id];
}

function pay(int $tontine, int $member, int $amount = 5000)
{
    return test()->postJson('/api/payments', [
        'tontine_id' => $tontine, 'tontine_member_id' => $member, 'amount' => $amount, 'method' => 'cash',
    ]);
}

it('records a payment in every kind of caisse, in a group too', function (array $fields) {
    if ($fields['group'] ?? false) {
        $fields['group_id'] = $this->postJson('/api/groups', ['name' => 'Kalaban'])->json('data.id');
        unset($fields['group']);
    }
    [$id, $member] = caisse($fields);

    pay($id, $member)->assertSuccessful();

    $this->getJson("/api/tontines/{$id}")->assertJsonPath('data.total_collected', 5000);
})->with([
    'tontine' => [['name' => 'Femmes', 'type' => 'rotating', 'amount' => 5000, 'frequency' => 'monthly']],
    'cotisation' => [['name' => 'Décès', 'type' => 'periodic', 'amount' => 5000, 'frequency' => 'monthly']],
    'une seule fois' => [['name' => 'Château', 'type' => 'periodic', 'amount' => 5000, 'frequency' => 'once']],
    'collecte' => [['name' => 'Mosquée', 'type' => 'fundraising', 'target_amount' => 1000000]],
    'caisse d\'association' => [['name' => 'Décès', 'type' => 'periodic', 'amount' => 5000, 'frequency' => 'monthly', 'group' => true]],
]);

it('opens the first round of an old caisse that had none', function () {
    $tontine = Tontine::create(['name' => 'Ancienne', 'amount' => 45000, 'frequency' => 'monthly', 'created_by' => $this->owner->id]);

    $this->getJson("/api/tontines/{$tontine->id}")->assertJsonCount(1, 'data.cycles');
});

it('lets only the bureau record or cancel a payment', function () {
    [$id, $member] = caisse(['name' => 'Femmes', 'amount' => 5000, 'frequency' => 'monthly']);
    $paymentId = pay($id, $member)->json('id');

    Sanctum::actingAs(User::factory()->create(['phone' => '70000099', 'pin_code' => '9999']));
    pay($id, $member)->assertForbidden();
    $this->deleteJson("/api/payments/{$paymentId}")->assertForbidden();

    Sanctum::actingAs($this->owner);
    $this->deleteJson("/api/payments/{$paymentId}")->assertSuccessful();
    expect(Payment::count())->toBe(0);
});

it('keeps a member who paid: pause instead of delete', function () {
    [$id, $member] = caisse(['name' => 'Femmes', 'amount' => 5000, 'frequency' => 'monthly']);
    pay($id, $member);

    $this->deleteJson("/api/tontines/{$id}/members/{$member}")->assertUnprocessable();
});

it('lets only the bureau manage members', function () {
    [$id, $member] = caisse(['name' => 'Femmes', 'amount' => 5000, 'frequency' => 'monthly']);

    Sanctum::actingAs(User::factory()->create(['phone' => '70000099', 'pin_code' => '9999']));
    $this->postJson("/api/tontines/{$id}/members", ['name' => 'Intrus', 'phone' => '70000098', 'role' => 'membre', 'order_position' => 2])->assertForbidden();
    $this->deleteJson("/api/tontines/{$id}/members/{$member}")->assertForbidden();
});

it('archives a caisse out of the list, and back', function () {
    [$id] = caisse(['name' => 'Femmes', 'amount' => 5000, 'frequency' => 'monthly']);

    $this->postJson("/api/tontines/{$id}/archive")->assertSuccessful();
    $this->getJson('/api/tontines')->assertJsonCount(0, 'data');
    $this->getJson('/api/tontines?archived=1')->assertJsonCount(1, 'data');

    $this->postJson("/api/tontines/{$id}/archive", ['archived' => false]);
    $this->getJson('/api/tontines')->assertJsonCount(1, 'data');
});

it('deletes a caisse with its history, by its creator only', function () {
    [$id, $member] = caisse(['name' => 'Femmes', 'amount' => 5000, 'frequency' => 'monthly']);
    pay($id, $member);
    Tontine::find($id)->cycles()->first()->update(['beneficiary_id' => $member]);

    Sanctum::actingAs(User::factory()->create(['phone' => '70000099', 'pin_code' => '9999']));
    $this->deleteJson("/api/tontines/{$id}")->assertForbidden();

    Sanctum::actingAs($this->owner);
    $this->deleteJson("/api/tontines/{$id}")->assertSuccessful();
    expect(Tontine::count())->toBe(0)->and(Payment::count())->toBe(0);
});

it('archives and deletes a group with its caisses', function () {
    $group = $this->postJson('/api/groups', ['name' => 'Kalaban'])->json('data.id');
    [$id, $member] = caisse(['name' => 'Décès', 'type' => 'periodic', 'amount' => 2000, 'frequency' => 'monthly', 'group_id' => $group]);
    pay($id, $member);

    $this->postJson("/api/groups/{$group}/archive")->assertSuccessful();
    $this->getJson('/api/groups')->assertJsonCount(0, 'data');
    expect(Tontine::find($id)->archived_at)->not->toBeNull();

    $this->deleteJson("/api/groups/{$group}")->assertSuccessful();
    expect(Group::count())->toBe(0)->and(Tontine::count())->toBe(0);
});

it('deletes the account with its caisses, after the PIN', function () {
    [$id, $member] = caisse(['name' => 'Femmes', 'amount' => 5000, 'frequency' => 'monthly']);
    pay($id, $member);

    $this->deleteJson('/api/account', ['pin_code' => '0000'])->assertUnprocessable();
    $this->deleteJson('/api/account', ['pin_code' => '1234'])->assertSuccessful();

    expect(User::count())->toBe(0)->and(Tontine::count())->toBe(0);
});

it('keeps the payments an account validated in other caisses', function () {
    $other = User::factory()->create(['phone' => '70000050', 'pin_code' => '5555']);
    $tontine = Tontine::create(['name' => 'Autre', 'amount' => 1000, 'frequency' => 'monthly', 'created_by' => $other->id]);
    $tontine->ensureFirstCycle();
    $member = $tontine->members()->create(['name' => 'Awa', 'phone' => '70000051', 'role' => 'membre', 'order_position' => 1]);
    Payment::create(['tontine_id' => $tontine->id, 'tontine_member_id' => $member->id, 'cycle_id' => $tontine->cycles()->first()->id, 'amount' => 1000, 'method' => 'cash', 'validated_by' => $this->owner->id, 'paid_at' => now()]);

    $this->deleteJson('/api/account', ['pin_code' => '1234'])->assertSuccessful();

    expect(Payment::first()->validated_by)->toBeNull();
});
