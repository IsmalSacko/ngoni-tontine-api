<?php

use App\Models\Group;
use App\Models\Tontine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create(['phone' => '+33609090990', 'pin_code' => '1234']);
    $this->group = Group::create(['name' => 'Ressortissants de Kalaban', 'currency' => 'EUR', 'created_by' => $this->owner->id]);
    $this->caisse = Tontine::create(['name' => 'Décès', 'type' => 'periodic', 'amount' => 20, 'frequency' => 'monthly', 'currency' => 'EUR', 'created_by' => $this->owner->id, 'group_id' => $this->group->id]);
    $this->newcomer = User::factory()->create(['name' => 'Bakary', 'phone' => '+33612345678', 'pin_code' => '0000']);
});

function invitationToken(): string
{
    Sanctum::actingAs(test()->owner);

    return test()->postJson('/api/groups/'.test()->group->id.'/invitation')
        ->assertOk()
        ->assertJsonPath('data.kind', 'group')
        ->json('data.token');
}

it('gives the bureau one stable invitation, and a new one on demand', function () {
    $token = invitationToken();
    expect($this->postJson("/api/groups/{$this->group->id}/invitation")->json('data.token'))->toBe($token);

    $fresh = $this->postJson("/api/invitations/{$token}/renew")->json('data.token');
    expect($fresh)->not->toBe($token);

    Sanctum::actingAs($this->newcomer);
    $this->getJson("/api/invitations/{$token}")->assertNotFound();
    $this->getJson("/api/invitations/{$fresh}")->assertOk()->assertJsonPath('data.name', 'Ressortissants de Kalaban');
});

it('lets someone ask to join; the bureau accepts and he is in every caisse', function () {
    $token = invitationToken();

    Sanctum::actingAs($this->newcomer);
    $this->postJson("/api/invitations/{$token}/join")->assertCreated();
    $this->getJson("/api/invitations/{$token}")->assertJsonPath('data.pending', true);
    // Pas encore membre : il n'a rien dans « Mes cotisations ».
    $this->getJson('/api/me/caisses')->assertJsonCount(0, 'data');

    Sanctum::actingAs($this->owner);
    $id = $this->getJson('/api/join-requests/pending')->assertJsonCount(1, 'data')->json('data.0.id');
    $this->postJson("/api/join-requests/{$id}/approve")->assertOk();

    Sanctum::actingAs($this->newcomer);
    $this->getJson('/api/me/caisses')->assertJsonCount(1, 'data')->assertJsonPath('data.0.caisse.name', 'Décès');
    $this->getJson("/api/invitations/{$token}")->assertJsonPath('data.already_member', true);
    $this->postJson("/api/invitations/{$token}/join")->assertStatus(409);
});

it('lets the bureau refuse', function () {
    $token = invitationToken();
    Sanctum::actingAs($this->newcomer);
    $this->postJson("/api/invitations/{$token}/join");

    Sanctum::actingAs($this->owner);
    $id = $this->getJson('/api/join-requests/pending')->json('data.0.id');
    $this->postJson("/api/join-requests/{$id}/reject")->assertOk();

    expect($this->group->members()->count())->toBe(0);
});

it('keeps the invitation and the decision to the bureau', function () {
    $token = invitationToken();
    Sanctum::actingAs($this->newcomer);
    $this->postJson("/api/groups/{$this->group->id}/invitation")->assertForbidden();
    $this->postJson("/api/invitations/{$token}/join");
    $this->getJson('/api/join-requests/pending')->assertJsonCount(0, 'data');
});

it('invites to a caisse outside any group', function () {
    $solo = Tontine::create(['name' => 'Tontine des femmes', 'amount' => 5000, 'frequency' => 'weekly', 'created_by' => $this->owner->id]);
    Sanctum::actingAs($this->owner);
    $token = $this->postJson("/api/tontines/{$solo->id}/invitation")->assertOk()->assertJsonPath('data.kind', 'caisse')->json('data.token');
    $this->postJson("/api/tontines/{$this->caisse->id}/invitation")->assertUnprocessable();

    Sanctum::actingAs($this->newcomer);
    $this->postJson("/api/invitations/{$token}/join")->assertCreated();
    Sanctum::actingAs($this->owner);
    $this->postJson('/api/join-requests/'.$this->getJson('/api/join-requests/pending')->json('data.0.id').'/approve')->assertOk();

    expect($solo->members()->where('name', 'Bakary')->exists())->toBeTrue();
});

it('serves the invitation page and the app link files', function () {
    $token = invitationToken();

    $this->get("/rejoindre/{$token}")->assertOk()->assertSee('Ressortissants de Kalaban')->assertSee('ngonitontine');
    $this->get('/rejoindre/inconnu')->assertOk()->assertSee('Lien plus valable');
    $this->getJson('/.well-known/assetlinks.json')->assertOk()->assertJsonPath('0.target.package_name', 'com.ismaeldev.fasoya.tontine');
    $this->getJson('/.well-known/apple-app-site-association')->assertOk()->assertJsonPath('applinks.details.0.paths.0', '/rejoindre/*');
});
