<?php

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function infrastructureAssetPayload(Client $client, Store $store, array $overrides = []): array
{
    return array_merge([
        'asset_code'     => 'PAN-RTR-001',
        'asset_type'     => AssetType::Infrastructure->value,
        'client_id'      => $client->id,
        'store_id'       => $store->id,
        'asset_name'     => 'Store Router',
        'manufacturer'   => 'Teltonika',
        'model'          => 'RUT956',
        'serial_number'  => '6003064210',
        'asset_status'   => 'active',
        'imei'           => '864431064720719',
        'mac_address'    => '20:97:27:44:EB:02',
        'wifi_ssid'      => 'Blak-WiFi',
        'wifi_password'  => 'Bandits008!',
        'admin_password' => 'Bl4K$88**',
        'sim_carrier'    => 'Amaysim',
        'sim_number'     => '0479038063',
    ], $overrides);
}

it('pm can create a router asset with network detail stored encrypted', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();
    $store  = Store::factory()->create(['client_id' => $client->id]);

    $this->actingAs($pm)
        ->post(route('assets.store'), infrastructureAssetPayload($client, $store))
        ->assertRedirect();

    $asset = Asset::where('asset_code', 'PAN-RTR-001')->firstOrFail();

    $this->assertDatabaseHas('asset_infrastructure_details', [
        'asset_id'    => $asset->id,
        'imei'        => '864431064720719',
        'mac_address' => '20:97:27:44:EB:02',
        'wifi_ssid'   => 'Blak-WiFi',
        'sim_carrier' => 'Amaysim',
        'sim_number'  => '0479038063',
    ]);

    // Ciphertext on disk, never the plaintext password.
    $rawRow = DB::table('asset_infrastructure_details')->where('asset_id', $asset->id)->first();
    expect($rawRow->wifi_password)->not->toContain('Bandits008!');
    expect($rawRow->admin_password)->not->toContain('Bl4K$88**');

    // Transparently decrypted through the model.
    $asset->load('infrastructureDetail');
    expect($asset->infrastructureDetail->wifi_password)->toBe('Bandits008!');
    expect($asset->infrastructureDetail->admin_password)->toBe('Bl4K$88**');
});

it('leaving the password fields blank on update keeps the existing secret', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();
    $store  = Store::factory()->create(['client_id' => $client->id]);

    $asset = Asset::factory()->forClientAndStore($client, $store)->ofType(AssetType::Infrastructure)->create([
        'asset_code' => 'PAN-RTR-002',
    ]);
    $asset->createDetail([
        'wifi_password'  => 'Bandits008!',
        'admin_password' => 'Bl4K$88**',
    ]);

    $payload = infrastructureAssetPayload($client, $store, [
        'asset_code'     => 'PAN-RTR-002',
        'wifi_password'  => '',
        'admin_password' => '',
    ]);

    $this->actingAs($pm)
        ->patch(route('assets.update', $asset), $payload)
        ->assertRedirect(route('assets.show', $asset));

    $asset->refresh()->load('infrastructureDetail');
    expect($asset->infrastructureDetail->wifi_password)->toBe('Bandits008!');
    expect($asset->infrastructureDetail->admin_password)->toBe('Bl4K$88**');
});

it('pm can reveal a router secret on demand and the reveal is audited', function () {
    $pm    = User::factory()->pm()->create();
    $asset = Asset::factory()->ofType(AssetType::Infrastructure)->create();
    $asset->createDetail(['wifi_password' => 'Bandits008!']);

    $response = $this->actingAs($pm)
        ->get(route('assets.infrastructure-detail.reveal', [$asset, 'wifi_password']))
        ->assertOk();

    expect($response->json('value'))->toBe('Bandits008!');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    $this->assertDatabaseHas('audit_logs', [
        'action'         => 'secret_revealed',
        'auditable_type' => $asset->getMorphClass(),
        'auditable_id'   => $asset->id,
        'user_id'        => $pm->id,
    ]);

    $entry = AuditLog::where('action', 'secret_revealed')->latest('id')->first();
    expect($entry->after)->toBe(['field' => 'wifi_password']);
    // Never persist the plaintext secret alongside the audit trail.
    expect(json_encode($entry->after))->not->toContain('Bandits008!');
});

it('client user cannot reveal a router secret', function () {
    $client = Client::factory()->create();
    $user   = User::factory()->clientUser()->create(['client_id' => $client->id]);
    $store  = Store::factory()->create(['client_id' => $client->id]);
    $asset  = Asset::factory()->forClientAndStore($client, $store)->ofType(AssetType::Infrastructure)->create();
    $asset->createDetail(['wifi_password' => 'Bandits008!']);

    $this->actingAs($user)
        ->get(route('assets.infrastructure-detail.reveal', [$asset, 'wifi_password']))
        ->assertForbidden();
});

it('rejects a reveal request for a field outside the allow-list', function () {
    $pm    = User::factory()->pm()->create();
    $asset = Asset::factory()->ofType(AssetType::Infrastructure)->create();
    $asset->createDetail(['wifi_password' => 'Bandits008!']);

    $this->actingAs($pm)
        ->get('/assets/'.$asset->id.'/infrastructure-detail/reveal/cable_type')
        ->assertNotFound();
});
