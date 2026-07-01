<?php

use App\Enums\JobStatus;
use App\Events\JobSlaAtRisk;
use App\Events\JobSlaBreached;
use App\Models\Client;
use App\Models\ServiceJob;
use App\Models\SlaProfile;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function slaTrackedJob(array $overrides = []): ServiceJob
{
    $profile = SlaProfile::factory()->create();
    $client  = Client::factory()->create(['sla_profile_id' => $profile->id]);
    $store   = Store::factory()->create(['client_id' => $client->id]);

    return ServiceJob::factory()->forClient($client, $store)->create(array_merge([
        'sla_profile_id'           => $profile->id,
        'sla_clock_started_at'     => now()->subHours(20),
        'sla_at_risk_at'           => now()->subHour(),
        'sla_resolution_target_at' => now()->addHour(),
        'sla_at_risk'              => false,
        'sla_breached'             => false,
    ], $overrides));
}

it('flags a job at-risk once its at-risk threshold has passed', function () {
    Event::fake([JobSlaAtRisk::class]);

    $job = slaTrackedJob();

    $this->artisan('sla:recompute')->assertSuccessful();

    expect($job->fresh()->sla_at_risk)->toBeTrue();
    expect($job->fresh()->sla_breached)->toBeFalse();
    Event::assertDispatched(JobSlaAtRisk::class, fn ($e) => $e->job->id === $job->id);
});

it('flags a job breached once its resolution target has passed', function () {
    Event::fake([JobSlaBreached::class]);

    $job = slaTrackedJob(['sla_resolution_target_at' => now()->subMinute()]);

    $this->artisan('sla:recompute')->assertSuccessful();

    expect($job->fresh()->sla_breached)->toBeTrue();
    Event::assertDispatched(JobSlaBreached::class, fn ($e) => $e->job->id === $job->id);
});

it('does not flag a job before its at-risk threshold', function () {
    $job = slaTrackedJob(['sla_at_risk_at' => now()->addHour()]);

    $this->artisan('sla:recompute')->assertSuccessful();

    expect($job->fresh()->sla_at_risk)->toBeFalse();
    expect($job->fresh()->sla_breached)->toBeFalse();
});

it('does not re-evaluate a terminal (validated) job', function () {
    $job = slaTrackedJob([
        'sla_resolution_target_at' => now()->subMinute(),
        'job_status'               => JobStatus::Validated->value,
    ]);

    $this->artisan('sla:recompute')->assertSuccessful();

    expect($job->fresh()->sla_breached)->toBeFalse();
});

it('writes an audit log entry when a job flips to at-risk', function () {
    $job = slaTrackedJob();

    $this->artisan('sla:recompute')->assertSuccessful();

    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => 'App\\Models\\ServiceJob',
        'auditable_id'   => $job->id,
        'action'         => 'updated',
    ]);
});
