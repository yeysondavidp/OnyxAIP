<?php

use App\Enums\ReportExportStatus;
use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Jobs\GenerateReportJob;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Store;
use App\Models\User;
use App\Services\Reports\Builders\AssetRegisterReportBuilder;
use App\Services\Reports\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('runs a small report synchronously', function () {
    Storage::fake('local');

    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();
    $store  = Store::factory()->create(['client_id' => $client->id]);
    Asset::factory()->forClientAndStore($client, $store)->count(5)->create();

    $export = app(ReportService::class)->run(
        ReportType::AssetRegister,
        ReportFormat::Csv,
        $client->id,
        $pm->id,
        [],
        app(AssetRegisterReportBuilder::class),
    );

    expect($export->status)->toBe(ReportExportStatus::Ready);
    expect($export->row_count)->toBe(5);
    expect($export->path)->not->toBeNull();
});

it('queues a large report instead of running it inline', function () {
    Queue::fake();

    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();
    $store  = Store::factory()->create(['client_id' => $client->id]);
    Asset::factory()->forClientAndStore($client, $store)->count(201)->create();

    $export = app(ReportService::class)->run(
        ReportType::AssetRegister,
        ReportFormat::Csv,
        $client->id,
        $pm->id,
        [],
        app(AssetRegisterReportBuilder::class),
    );

    expect($export->status)->toBe(ReportExportStatus::Queued);
    expect($export->path)->toBeNull();
    Queue::assertPushed(GenerateReportJob::class, fn ($job) => $job->reportExportId === $export->id);
});
