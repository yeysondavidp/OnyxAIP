<?php

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\Orientation;
use App\Enums\TotemSuppliedBy;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function baseAssetPayload(Client $client, Store $store, array $overrides = []): array
{
    return array_merge([
        'asset_code'   => 'PAN-SCR-001',
        'asset_type'   => AssetType::DigitalScreen->value,
        'client_id'    => $client->id,
        'store_id'     => $store->id,
        'asset_name'   => 'Pandora Window Screen 1',
        'manufacturer' => 'Samsung',
        'model'        => 'QH98C',
        'asset_status' => AssetStatus::Active->value,
        // Screen detail
        'screen_size_inches' => '98.0',
        'resolution_width'   => 3840,
        'resolution_height'  => 2160,
        'orientation'        => Orientation::Landscape->value,
        'mount_type'         => 'Floor Totem',
        'totem_supplied_by'  => TotemSuppliedBy::Onyx->value,
    ], $overrides);
}

// ── Index ─────────────────────────────────────────────────────────────────────

it('pm can view the asset list', function () {
    $pm = User::factory()->pm()->create();
    Asset::factory()->create(['asset_name' => 'Test Screen']);

    $this->actingAs($pm)
        ->get(route('assets.index'))
        ->assertOk()
        ->assertSee('Asset Registry');
});

it('technician cannot view the asset list', function () {
    $tech = User::factory()->technician()->create();

    $this->actingAs($tech)
        ->get(route('assets.index'))
        ->assertForbidden();
});

// ── Create / Store ────────────────────────────────────────────────────────────

it('pm can create a digital screen asset with type detail', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();
    $store  = Store::factory()->create(['client_id' => $client->id]);

    $this->actingAs($pm)
        ->post(route('assets.store'), baseAssetPayload($client, $store))
        ->assertRedirect();

    $this->assertDatabaseHas('assets', [
        'asset_code'   => 'PAN-SCR-001',
        'asset_type'   => 'digital_screen',
        'client_id'    => $client->id,
        'store_id'     => $store->id,
        'asset_status' => 'active',
    ]);

    $asset = Asset::where('asset_code', 'PAN-SCR-001')->first();
    $this->assertNotNull($asset);

    $this->assertDatabaseHas('asset_screen_details', [
        'asset_id'          => $asset->id,
        'orientation'       => 'landscape',
        'totem_supplied_by' => 'onyx',
    ]);
});

it('asset_code must be unique', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();
    $store  = Store::factory()->create(['client_id' => $client->id]);

    Asset::factory()->forClientAndStore($client, $store)->create(['asset_code' => 'PAN-SCR-001']);

    $this->actingAs($pm)
        ->post(route('assets.store'), baseAssetPayload($client, $store))
        ->assertSessionHasErrors('asset_code');
});

it('store must belong to the chosen client', function () {
    $pm      = User::factory()->pm()->create();
    $clientA = Client::factory()->create();
    $clientB = Client::factory()->create();
    $storeB  = Store::factory()->create(['client_id' => $clientB->id]);

    $this->actingAs($pm)
        ->post(route('assets.store'), baseAssetPayload($clientA, $storeB))
        ->assertSessionHasErrors('store_id');
});

it('digital screen detail is required when type is digital_screen', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();
    $store  = Store::factory()->create(['client_id' => $client->id]);

    $payload = baseAssetPayload($client, $store, [
        'screen_size_inches' => null,
        'resolution_width'   => null,
        'resolution_height'  => null,
        'orientation'        => null,
        'mount_type'         => null,
        'totem_supplied_by'  => null,
    ]);

    $this->actingAs($pm)
        ->post(route('assets.store'), $payload)
        ->assertSessionHasErrors(['screen_size_inches', 'orientation']);
});

it('technician cannot create an asset', function () {
    $tech   = User::factory()->technician()->create();
    $client = Client::factory()->create();
    $store  = Store::factory()->create(['client_id' => $client->id]);

    $this->actingAs($tech)
        ->post(route('assets.store'), baseAssetPayload($client, $store))
        ->assertForbidden();
});

// ── Show ──────────────────────────────────────────────────────────────────────

it('pm can view an asset detail page', function () {
    $pm    = User::factory()->pm()->create();
    $asset = Asset::factory()->create(['asset_name' => 'Samsung QH98C']);

    $this->actingAs($pm)
        ->get(route('assets.show', $asset))
        ->assertOk()
        ->assertSee('Samsung QH98C');
});

it('cross-tenant asset detail is inaccessible', function () {
    $clientA = Client::factory()->create();
    $clientB = Client::factory()->create();
    $assetB  = Asset::factory()->create(['client_id' => $clientB->id]);

    $userA = User::factory()->clientUser()->create(['client_id' => $clientA->id]);

    $response = $this->actingAs($userA)->get(route('assets.show', $assetB));
    expect(in_array($response->status(), [403, 404]))->toBeTrue();
});

// ── Edit / Update ─────────────────────────────────────────────────────────────

it('pm can update an asset', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();
    $store  = Store::factory()->create(['client_id' => $client->id]);
    $asset  = Asset::factory()->forClientAndStore($client, $store)->create([
        'asset_code' => 'OLD-001',
        'asset_name' => 'Old Name',
        'asset_type' => AssetType::DigitalScreen->value,
    ]);
    $asset->createDetail([
        'screen_size_inches' => '55.0',
        'resolution_width'   => 1920,
        'resolution_height'  => 1080,
        'orientation'        => 'landscape',
        'mount_type'         => 'Wall Mount',
        'totem_supplied_by'  => 'client',
    ]);

    $payload = baseAssetPayload($client, $store, [
        'asset_code' => 'OLD-001',
        'asset_name' => 'New Name',
    ]);

    $this->actingAs($pm)
        ->patch(route('assets.update', $asset), $payload)
        ->assertRedirect(route('assets.show', $asset));

    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'asset_name' => 'New Name']);
});

// ── Destroy / Decommission ────────────────────────────────────────────────────

it('pm can decommission an asset (status change, not hard delete)', function () {
    $pm    = User::factory()->pm()->create();
    $asset = Asset::factory()->create(['asset_status' => AssetStatus::Active->value]);

    $this->actingAs($pm)
        ->delete(route('assets.destroy', $asset))
        ->assertRedirect(route('assets.index'));

    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'asset_status' => 'decommissioned']);
    $this->assertDatabaseCount('assets', 1);
});

it('technician cannot decommission an asset', function () {
    $tech  = User::factory()->technician()->create();
    $asset = Asset::factory()->create();

    $this->actingAs($tech)
        ->delete(route('assets.destroy', $asset))
        ->assertForbidden();
});
