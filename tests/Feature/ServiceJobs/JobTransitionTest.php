<?php

use App\Enums\JobStatus;
use App\Models\Client;
use App\Models\ServiceJob;
use App\Models\Store;
use App\Models\User;
use App\Services\JobTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Permitted transitions ─────────────────────────────────────────────────────

it('permits draft → invited', function () {
    $service = new JobTransitionService;

    expect($service->isPermitted(JobStatus::Draft, JobStatus::Invited))->toBeTrue();
});

it('permits invited → accepted', function () {
    $service = new JobTransitionService;

    expect($service->isPermitted(JobStatus::Invited, JobStatus::Accepted))->toBeTrue();
});

it('permits in_progress → completed', function () {
    $service = new JobTransitionService;

    expect($service->isPermitted(JobStatus::InProgress, JobStatus::Completed))->toBeTrue();
});

it('permits completed → validated', function () {
    $service = new JobTransitionService;

    expect($service->isPermitted(JobStatus::Completed, JobStatus::Validated))->toBeTrue();
});

it('permits completed → requires_remediation', function () {
    $service = new JobTransitionService;

    expect($service->isPermitted(JobStatus::Completed, JobStatus::RequiresRemediation))->toBeTrue();
});

// ── Illegal transitions ───────────────────────────────────────────────────────

it('rejects draft → validated (skip)', function () {
    $service = new JobTransitionService;
    $pm      = User::factory()->pm()->create();
    $store   = Store::factory()->create();
    $job     = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->draft()->create();

    expect(fn () => $service->transitionTo($job, JobStatus::Validated, $pm))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects validated → any further transition', function () {
    $service = new JobTransitionService;
    $pm      = User::factory()->pm()->create();
    $store   = Store::factory()->create();
    $job     = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->validated()->create();

    foreach ([JobStatus::Invited, JobStatus::InProgress, JobStatus::Cancelled] as $bad) {
        expect(fn () => $service->transitionTo($job, $bad, $pm))
            ->toThrow(InvalidArgumentException::class);
    }
});

it('transition persists to the database', function () {
    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();
    $job   = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->draft()->create();

    (new JobTransitionService)->transitionTo($job, JobStatus::Invited, $pm);

    expect($job->fresh()->job_status)->toBe(JobStatus::Invited);
});

// ── Validate / flagRemediation via HTTP ───────────────────────────────────────

it('pm can validate a completed job', function () {
    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();
    $job   = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->completed()->create();

    $this->actingAs($pm)
        ->post(route('jobs.validate', $job))
        ->assertRedirect();

    expect($job->fresh()->job_status)->toBe(JobStatus::Validated);
});

it('pm can flag a completed job for remediation with a reason', function () {
    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();
    $job   = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->completed()->create();

    $this->actingAs($pm)
        ->post(route('jobs.flag-remediation', $job), ['reason' => 'Screen still not powering on.'])
        ->assertRedirect();

    expect($job->fresh()->job_status)->toBe(JobStatus::RequiresRemediation);
});

it('flagging remediation without a reason is rejected', function () {
    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();
    $job   = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->completed()->create();

    $this->actingAs($pm)
        ->post(route('jobs.flag-remediation', $job))
        ->assertSessionHasErrors('reason');

    expect($job->fresh()->job_status)->toBe(JobStatus::Completed);
});
