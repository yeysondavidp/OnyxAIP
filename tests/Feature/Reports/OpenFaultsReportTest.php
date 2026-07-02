<?php

use App\Models\Asset;
use App\Models\Client;
use App\Models\ReportExport;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('lists only the requested client\'s faulty and offline assets', function () {
    Storage::fake('local');

    $pm = User::factory()->pm()->create();

    $clientA = Client::factory()->create();
    $storeA  = Store::factory()->create(['client_id' => $clientA->id]);
    $faultyA = Asset::factory()->forClientAndStore($clientA, $storeA)->faulty()->create();
    Asset::factory()->forClientAndStore($clientA, $storeA)->create(); // active, excluded

    $clientB = Client::factory()->create();
    $storeB  = Store::factory()->create(['client_id' => $clientB->id]);
    $faultyB = Asset::factory()->forClientAndStore($clientB, $storeB)->faulty()->create();

    $this->actingAs($pm)->post(route('reports.open-faults.store'), [
        'client_id' => $clientA->id,
    ])->assertRedirect(route('reports.index'));

    $export = ReportExport::where('report_type', 'open_faults')->firstOrFail();
    expect($export->row_count)->toBe(1);

    $csv = Storage::disk('local')->get($export->path);
    expect($csv)->toContain($faultyA->asset_code);
    expect($csv)->not->toContain($faultyB->asset_code);
});
