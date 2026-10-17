<?php

use App\Models\Tontine;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('recognises the same number written two ways', function (string $a, string $b, bool $same) {
    expect(Phone::same($a, $b))->toBe($same);
})->with([
    'local et international (Mali)' => ['76 00 00 01', '+223 76000001', true],
    '00 et +' => ['0022376000001', '+22376000001', true],
    'France, 0 national' => ['06 09 09 09 90', '+33 6 09 09 09 90', true],
    'deux numéros différents' => ['76000001', '+22376000002', false],
    'trop court pour être reconnu' => ['0001', '+2230001', false],
]);

it('logs in with the international number of a legacy local account', function () {
    User::factory()->create(['phone' => '76000001', 'pin_code' => '1234']);

    $this->postJson('/api/login', ['phone' => '+22376000001', 'pin_code' => '1234'])->assertOk();
    $this->postJson('/api/login', ['phone' => '+22376000001', 'pin_code' => '9999'])->assertUnauthorized();
    $this->postJson('/api/login', ['phone' => '+22399999999', 'pin_code' => '1234'])->assertUnauthorized();
});

it('refuses a second account for the same number written otherwise', function () {
    User::factory()->create(['phone' => '+22376000001', 'pin_code' => '1234']);

    $this->postJson('/api/register', ['name' => 'Moussa', 'phone' => '76 00 00 01', 'pin_code' => '1234'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

it('recognises the presidente whatever the writing of her number', function () {
    $owner = User::factory()->create(['phone' => '+33609090990', 'pin_code' => '1234']);
    $tontine = Tontine::create(['name' => 'Décès', 'type' => 'periodic', 'amount' => 20, 'frequency' => 'monthly', 'currency' => 'EUR', 'created_by' => $owner->id]);
    $tontine->members()->create(['name' => 'Awa', 'phone' => '0612345678', 'role' => 'presidente', 'order_position' => 1]);

    expect($tontine->isManagedBy(User::factory()->create(['phone' => '+33612345678', 'pin_code' => '0000'])))->toBeTrue()
        ->and($tontine->isManagedBy(User::factory()->create(['phone' => '+33699999999', 'pin_code' => '0000'])))->toBeFalse();
});
