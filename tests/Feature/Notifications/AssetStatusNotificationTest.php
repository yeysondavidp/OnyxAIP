<?php

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Store;
use App\Models\User;
use App\Notifications\PmNotification;
use App\Services\AssetTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('fires exactly one assetStatusChanged notification via AssetTransitionService', function () {
    Notification::fake();

    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();
    $asset = Asset::factory()->create([
        'store_id'     => $store->id,
        'client_id'    => $store->client_id,
        'asset_type'   => AssetType::DigitalScreen->value,
        'asset_status' => AssetStatus::Active->value,
    ]);

    app(AssetTransitionService::class)->transitionTo($asset, AssetStatus::Faulty, $pm);

    Notification::assertSentToTimes($pm, PmNotification::class, 2); // status-changed + new-fault
});

it('fires the notification for a PM manual edit that bypasses AssetTransitionService', function () {
    Notification::fake();

    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();
    $asset = Asset::factory()->create([
        'store_id'     => $store->id,
        'client_id'    => $store->client_id,
        'asset_type'   => AssetType::DigitalScreen->value,
        'asset_status' => AssetStatus::Active->value,
    ]);

    $this->actingAs($pm)->delete(route('assets.destroy', $asset)); // decommission path

    Notification::assertSentToTimes($pm, PmNotification::class, 1); // status-changed only, no new-fault (not Faulty)
});

it('does not fire a notification when an edit does not change asset_status', function () {
    Notification::fake();

    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();
    $store  = Store::factory()->create(['client_id' => $client->id]);
    $asset  = Asset::factory()->forClientAndStore($client, $store)->create([
        'asset_code'   => 'PAN-SCR-001',
        'asset_type'   => AssetType::DigitalScreen->value,
        'asset_status' => AssetStatus::Active->value,
    ]);
    $asset->createDetail([
        'screen_size_inches' => '98.0',
        'resolution_width'   => 3840,
        'resolution_height'  => 2160,
        'orientation'        => 'landscape',
        'mount_type'         => 'Floor Totem',
        'totem_supplied_by'  => 'onyx',
    ]);

    $this->actingAs($pm)->patch(route('assets.update', $asset), [
        'asset_code'         => 'PAN-SCR-001',
        'asset_type'         => AssetType::DigitalScreen->value,
        'client_id'          => $client->id,
        'store_id'           => $store->id,
        'asset_name'         => 'Renamed asset',
        'manufacturer'       => 'Samsung',
        'model'              => 'QH98C',
        'asset_status'       => AssetStatus::Active->value,
        'screen_size_inches' => '98.0',
        'resolution_width'   => 3840,
        'resolution_height'  => 2160,
        'orientation'        => 'landscape',
        'mount_type'         => 'Floor Totem',
        'totem_supplied_by'  => 'onyx',
    ]);

    Notification::assertNothingSentTo($pm);
});
