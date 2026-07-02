<?php

use App\Models\Client;
use App\Models\ReportExport;
use App\Models\ServiceJob;
use App\Models\SlaProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('reads the stored sla breach state without recomputing it', function () {
    Storage::fake('local');

    $pm      = User::factory()->pm()->create();
    $profile = SlaProfile::factory()->create();
    $client  = Client::factory()->create(['sla_profile_id' => $profile->id]);
    $store   = Store::factory()->create(['client_id' => $client->id]);

    $job = ServiceJob::factory()->forClient($client, $store)->create([
        'sla_profile_id'           => $profile->id,
        'scheduled_date'           => now()->format('Y-m-d'),
        'sla_clock_started_at'     => now()->subHours(50),
        'sla_resolution_target_at' => now()->subHour(),
        'sla_at_risk'              => true,
        'sla_breached'             => true,
    ]);

    $this->actingAs($pm)->post(route('reports.sla-compliance.store'), [
        'client_id' => $client->id,
        'date_from' => now()->subDay()->format('Y-m-d'),
        'date_to'   => now()->addDay()->format('Y-m-d'),
    ])->assertRedirect(route('reports.index'));

    $export = ReportExport::where('report_type', 'sla_compliance')->firstOrFail();
    expect($export->row_count)->toBe(1);

    $csv = Storage::disk('local')->get($export->path);
    expect($csv)->toContain($job->job_reference);
    expect($csv)->toContain('Breached');
});

it('rejects an end date before the start date', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();

    $this->actingAs($pm)->post(route('reports.sla-compliance.store'), [
        'client_id' => $client->id,
        'date_from' => '2026-07-10',
        'date_to'   => '2026-07-01',
    ])->assertSessionHasErrors('date_to');
});
