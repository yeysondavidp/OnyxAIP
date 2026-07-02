<?php

use App\Enums\ReportExportStatus;
use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Models\Client;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function readyExport(array $overrides = []): ReportExport
{
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();

    return ReportExport::create(array_merge([
        'report_type'          => ReportType::AssetRegister->value,
        'format'               => ReportFormat::Csv->value,
        'client_id'            => $client->id,
        'requested_by_user_id' => $pm->id,
        'params'               => [],
        'status'               => ReportExportStatus::Ready->value,
        'disk'                 => 'local',
        'path'                 => 'asset_register/test.csv',
        'row_count'            => 1,
        'expires_at'           => now()->addHours(72),
    ], $overrides));
}

it('rejects an unsigned download request', function () {
    $export = readyExport();

    $this->get(route('reports.download', ['reportExport' => $export->id]))->assertForbidden();
});

it('serves the file via a valid signed url', function () {
    Storage::fake('local');
    Storage::disk('local')->put('asset_register/test.csv', "a,b\n1,2");

    $export = readyExport();
    $url    = URL::temporarySignedRoute('reports.download', $export->expires_at, ['reportExport' => $export->id]);

    $this->get($url)->assertOk();
});

it('returns 410 once the export has expired', function () {
    Storage::fake('local');
    Storage::disk('local')->put('asset_register/test.csv', "a,b\n1,2");

    $export = readyExport(['expires_at' => now()->subHour()]);
    $url    = URL::temporarySignedRoute('reports.download', now()->addMinutes(5), ['reportExport' => $export->id]);

    $this->get($url)->assertStatus(410);
});

it('returns 404 for a report that has not finished generating', function () {
    $export = readyExport(['status' => ReportExportStatus::Processing->value, 'path' => null]);
    $url    = URL::temporarySignedRoute('reports.download', $export->expires_at, ['reportExport' => $export->id]);

    $this->get($url)->assertNotFound();
});
