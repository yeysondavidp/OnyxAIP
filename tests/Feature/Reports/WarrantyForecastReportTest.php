<?php

use App\Models\Asset;
use App\Models\Client;
use App\Models\ReportExport;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('lists assets whose warranty falls within the date range', function () {
    Storage::fake('local');

    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();
    $store  = Store::factory()->create(['client_id' => $client->id]);

    $expiringSoon = Asset::factory()->forClientAndStore($client, $store)->create([
        'warranty_expiry' => now()->addDays(20)->format('Y-m-d'),
    ]);
    $expiringLater = Asset::factory()->forClientAndStore($client, $store)->create([
        'warranty_expiry' => now()->addYears(2)->format('Y-m-d'),
    ]);

    $this->actingAs($pm)->post(route('reports.warranty-forecast.store'), [
        'client_id' => $client->id,
        'date_from' => now()->format('Y-m-d'),
        'date_to'   => now()->addDays(30)->format('Y-m-d'),
    ])->assertRedirect(route('reports.index'));

    $export = ReportExport::where('report_type', 'warranty_expiry_forecast')->firstOrFail();
    expect($export->row_count)->toBe(1);

    $csv = Storage::disk('local')->get($export->path);
    expect($csv)->toContain($expiringSoon->asset_code);
    expect($csv)->not->toContain($expiringLater->asset_code);
});
