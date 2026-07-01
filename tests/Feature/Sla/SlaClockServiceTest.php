<?php

use App\Enums\EarlyStartWindow;
use App\Enums\JobType;
use App\Models\Client;
use App\Models\ServiceJob;
use App\Models\SlaProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('starts the sla clock when a fault job is created against a client with an active profile', function () {
    $pm      = User::factory()->pm()->create();
    $profile = SlaProfile::factory()->create(['resolution_hours' => 10]);
    $client  = Client::factory()->create(['sla_profile_id' => $profile->id]);
    $store   = Store::factory()->create(['client_id' => $client->id]);

    $this->actingAs($pm)
        ->post(route('jobs.store'), [
            'store_id'           => $store->id,
            'job_reference'      => 'JOB-SLA-001',
            'job_name'           => 'Screen dead',
            'job_description'    => 'Screen not powering on.',
            'job_type'           => JobType::FaultRepair->value,
            'early_start_window' => EarlyStartWindow::Anytime->value,
        ])
        ->assertRedirect();

    $job = ServiceJob::where('job_reference', 'JOB-SLA-001')->firstOrFail();

    expect($job->sla_profile_id)->toBe($profile->id);
    expect($job->sla_clock_started_at)->not->toBeNull();
    expect($job->sla_resolution_target_at)->not->toBeNull();
    expect($job->sla_at_risk_at)->not->toBeNull();
    expect($job->sla_resolution_target_at->gt($job->sla_clock_started_at))->toBeTrue();
    expect($job->sla_at_risk_at->lt($job->sla_resolution_target_at))->toBeTrue();
});

it('does not start the sla clock for a non-fault job', function () {
    $pm      = User::factory()->pm()->create();
    $profile = SlaProfile::factory()->create();
    $client  = Client::factory()->create(['sla_profile_id' => $profile->id]);
    $store   = Store::factory()->create(['client_id' => $client->id]);

    $this->actingAs($pm)
        ->post(route('jobs.store'), [
            'store_id'           => $store->id,
            'job_reference'      => 'JOB-SLA-002',
            'job_name'           => 'Routine check',
            'job_description'    => 'Quarterly inspection.',
            'job_type'           => JobType::RoutineMaintenance->value,
            'early_start_window' => EarlyStartWindow::Anytime->value,
        ])
        ->assertRedirect();

    $job = ServiceJob::where('job_reference', 'JOB-SLA-002')->firstOrFail();

    expect($job->sla_clock_started_at)->toBeNull();
});

it('does not start the sla clock when the client has no sla profile', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create(['sla_profile_id' => null]);
    $store  = Store::factory()->create(['client_id' => $client->id]);

    $this->actingAs($pm)
        ->post(route('jobs.store'), [
            'store_id'           => $store->id,
            'job_reference'      => 'JOB-SLA-003',
            'job_name'           => 'Fault report',
            'job_description'    => 'No power.',
            'job_type'           => JobType::FaultRepair->value,
            'early_start_window' => EarlyStartWindow::Anytime->value,
        ])
        ->assertRedirect();

    $job = ServiceJob::where('job_reference', 'JOB-SLA-003')->firstOrFail();

    expect($job->sla_clock_started_at)->toBeNull();
    expect($job->sla_profile_id)->toBeNull();
});
