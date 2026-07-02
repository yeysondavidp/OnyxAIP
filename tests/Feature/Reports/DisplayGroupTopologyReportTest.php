<?php

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Client;
use App\Models\DisplayGroup;
use App\Models\ReportExport;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('pm can generate a display group topology pdf for a store', function () {
    Storage::fake('local');

    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();
    $store  = Store::factory()->create(['client_id' => $client->id]);

    $player = Asset::factory()->forClientAndStore($client, $store)->ofType(AssetType::MediaPlayer)->create();
    $screen = Asset::factory()->forClientAndStore($client, $store)->ofType(AssetType::DigitalScreen)->create();
    $group  = DisplayGroup::factory()->forStore($store, $player)->create();
    $group->screens()->attach($screen->id);

    // An asset at the store but not in any group.
    Asset::factory()->forClientAndStore($client, $store)->create();

    $this->actingAs($pm)->post(route('reports.display-group-topology.store'), [
        'store_id' => $store->id,
    ])->assertRedirect(route('reports.index'));

    $export = ReportExport::where('report_type', 'display_group_topology')->firstOrFail();
    expect($export->status->value)->toBe('ready');
    expect($export->row_count)->toBe(1);
    expect(Storage::disk('local')->exists($export->path))->toBeTrue();
});

it('store manager cannot generate a topology report for another client\'s store', function () {
    $clientA        = Client::factory()->create();
    $clientB        = Client::factory()->create();
    $storeB         = Store::factory()->create(['client_id' => $clientB->id]);
    $restrictedUser = User::factory()->clientUser()->create(['client_id' => $clientA->id]);

    expect($restrictedUser->can('view', $storeB))->toBeFalse();
});
