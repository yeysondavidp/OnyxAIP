<?php

use App\Models\Client;
use App\Models\JobCheckpoint;
use App\Models\ReportExport;
use App\Models\ServiceJob;
use App\Models\Store;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('computes worked hours from job checkpoint timestamps', function () {
    Storage::fake('local');

    $pm         = User::factory()->pm()->create();
    $client     = Client::factory()->create();
    $store      = Store::factory()->create(['client_id' => $client->id]);
    $job        = ServiceJob::factory()->forClient($client, $store)->create();
    $technician = TechnicianProfile::factory()->create(['name' => 'Michael Chen']);

    JobCheckpoint::create([
        'job_id'                => $job->id,
        'technician_profile_id' => $technician->id,
        'start_timestamp_utc'   => now()->subHours(3),
        'end_timestamp_utc'     => now(),
    ]);

    $this->actingAs($pm)->post(route('reports.technician-hours.store'), [
        'date_from' => now()->subDay()->format('Y-m-d'),
        'date_to'   => now()->addDay()->format('Y-m-d'),
    ])->assertRedirect(route('reports.index'));

    $export = ReportExport::where('report_type', 'technician_hours')->firstOrFail();
    expect($export->row_count)->toBe(1);
    expect($export->client_id)->toBeNull();

    $csv = Storage::disk('local')->get($export->path);
    expect($csv)->toContain('Michael Chen');
    expect($csv)->toContain('3.00');
    expect($csv)->toContain('TOTAL BY TECHNICIAN');
});

it('technician role cannot run the technician hours report', function () {
    $tech = User::factory()->technician()->create();

    $this->actingAs($tech)->get(route('reports.technician-hours.create'))->assertForbidden();
});
