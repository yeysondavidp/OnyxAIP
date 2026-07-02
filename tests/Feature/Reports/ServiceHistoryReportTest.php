<?php

use App\Enums\AssetStatus;
use App\Enums\JobType;
use App\Models\Asset;
use App\Models\Client;
use App\Models\ReportExport;
use App\Models\ServiceHistory;
use App\Models\ServiceJob;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function seedServiceHistory(Asset $asset, ServiceJob $job): ServiceHistory
{
    return ServiceHistory::create([
        'asset_id'               => $asset->id,
        'service_job_id'         => $job->id,
        'service_date'           => now()->format('Y-m-d'),
        'technician_profile_ids' => [],
        'job_type'               => JobType::RoutineMaintenance->value,
        'status_before'          => AssetStatus::Faulty->value,
        'status_after'           => AssetStatus::Active->value,
        'technician_notes'       => 'Replaced panel.',
        'before_photo_paths'     => [],
        'after_photo_paths'      => [],
        'created_at'             => now(),
    ]);
}

it('pm can generate a per-asset service history pdf', function () {
    Storage::fake('local');

    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();
    $store  = Store::factory()->create(['client_id' => $client->id]);
    $asset  = Asset::factory()->forClientAndStore($client, $store)->create();
    $job    = ServiceJob::factory()->forClient($client, $store)->validated()->create();
    seedServiceHistory($asset, $job);

    $this->actingAs($pm)->post(route('reports.service-history.store'), [
        'report_kind' => 'asset_pdf',
        'asset_id'    => $asset->id,
    ])->assertRedirect(route('reports.index'));

    $export = ReportExport::where('report_type', 'service_history_asset')->firstOrFail();
    expect($export->row_count)->toBe(1);
});

it('per-store csv only includes that store\'s history, not another store\'s', function () {
    Storage::fake('local');

    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();
    $storeA = Store::factory()->create(['client_id' => $client->id]);
    $storeB = Store::factory()->create(['client_id' => $client->id]);

    $assetA = Asset::factory()->forClientAndStore($client, $storeA)->create();
    $assetB = Asset::factory()->forClientAndStore($client, $storeB)->create();
    $jobA   = ServiceJob::factory()->forClient($client, $storeA)->validated()->create();
    $jobB   = ServiceJob::factory()->forClient($client, $storeB)->validated()->create();

    seedServiceHistory($assetA, $jobA);
    seedServiceHistory($assetB, $jobB);

    $this->actingAs($pm)->post(route('reports.service-history.store'), [
        'report_kind' => 'store_csv',
        'store_id'    => $storeA->id,
    ])->assertRedirect(route('reports.index'));

    $export = ReportExport::where('report_type', 'service_history_store')->firstOrFail();
    expect($export->row_count)->toBe(1);

    $csv = Storage::disk('local')->get($export->path);
    expect($csv)->toContain($assetA->asset_code);
    expect($csv)->not->toContain($assetB->asset_code);
});
