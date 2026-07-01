<?php

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Livewire\ServiceHistory\StoreHistoryLog;
use App\Models\Asset;
use App\Models\Client;
use App\Models\ServiceJob;
use App\Models\Store;
use App\Models\User;
use App\Services\JobValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function validateJobWithAsset(Store $store): array
{
    $client = Client::find($store->client_id);
    // Pin scheduled_date so resolveServiceDate()'s fallback (no checkpoint exists here)
    // is deterministic — the factory default is a random date up to 30 days out.
    $job = ServiceJob::factory()->forClient($client, $store)->completed()->create([
        'scheduled_date' => now()->toDateString(),
    ]);
    $asset = Asset::factory()->create([
        'store_id'   => $store->id, 'client_id' => $store->client_id,
        'asset_type' => AssetType::DigitalScreen->value, 'asset_status' => AssetStatus::UnderMaintenance->value,
    ]);
    $job->assets()->attach($asset->id, ['status_before' => 'active']);

    app(JobValidationService::class)->validate($job, User::factory()->pm()->create());

    return [$job, $asset];
}

// ── Asset detail page (US-11.3) ───────────────────────────────────────────────

it('asset detail page shows an empty state when there is no service history', function () {
    $pm    = User::factory()->pm()->create();
    $asset = Asset::factory()->create();

    $this->actingAs($pm)
        ->get(route('assets.show', $asset))
        ->assertOk()
        ->assertSee('No service history yet');
});

it('asset detail page shows a validated job in its service history log', function () {
    $pm            = User::factory()->pm()->create();
    $store         = Store::factory()->create();
    [$job, $asset] = validateJobWithAsset($store);

    $this->actingAs($pm)
        ->get(route('assets.show', $asset))
        ->assertOk()
        ->assertSee($job->job_reference);
});

// ── Store aggregated view (US-11.4) ───────────────────────────────────────────

it('store service history livewire component lists validated entries', function () {
    $store         = Store::factory()->create();
    [$job, $asset] = validateJobWithAsset($store);

    Livewire::test(StoreHistoryLog::class, ['store' => $store])
        ->assertSee($asset->asset_code)
        ->assertSee($job->job_reference);
});

it('store service history filters by asset type', function () {
    $store         = Store::factory()->create();
    [$job, $asset] = validateJobWithAsset($store);

    Livewire::test(StoreHistoryLog::class, ['store' => $store])
        ->set('assetType', AssetType::MediaPlayer->value) // asset is a DigitalScreen, so this filters it out
        ->assertDontSee($asset->asset_code);
});

it('store service history filters by date range', function () {
    $store         = Store::factory()->create();
    [$job, $asset] = validateJobWithAsset($store);

    Livewire::test(StoreHistoryLog::class, ['store' => $store])
        ->set('dateFrom', now()->addDays(5)->format('Y-m-d')) // future — excludes today's entry
        ->assertDontSee($asset->asset_code);
});

it('store dashboard page renders the service history section', function () {
    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();
    validateJobWithAsset($store);

    $this->actingAs($pm)
        ->get(route('stores.show', $store))
        ->assertOk()
        ->assertSee('Service History');
});
