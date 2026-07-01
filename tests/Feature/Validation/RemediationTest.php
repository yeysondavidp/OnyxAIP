<?php

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\JobStatus;
use App\Models\Asset;
use App\Models\Client;
use App\Models\ServiceJob;
use App\Models\Store;
use App\Models\User;
use App\Services\JobValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Spawning the remediation sub-job (US-11.2) ────────────────────────────────

it('flagging remediation creates a Draft sub-job carrying client, store and assets forward', function () {
    $store  = Store::factory()->create();
    $client = Client::find($store->client_id);
    $job    = ServiceJob::factory()->forClient($client, $store)->completed()->create(['job_level' => 0]);
    $asset  = Asset::factory()->create([
        'store_id'   => $store->id, 'client_id' => $store->client_id,
        'asset_type' => AssetType::DigitalScreen->value, 'asset_status' => AssetStatus::UnderMaintenance->value,
    ]);
    $job->assets()->attach($asset->id, ['status_before' => 'active']);

    $pm          = User::factory()->pm()->create();
    $remediation = app(JobValidationService::class)->flagRemediation($job, $pm, 'Screen still black after visit.');

    expect($remediation->job_status)->toBe(JobStatus::Draft);
    expect($remediation->job_level)->toBe(1);
    expect($remediation->parent_job_id)->toBe($job->id);
    expect($remediation->client_id)->toBe($job->client_id);
    expect($remediation->store_id)->toBe($job->store_id);
    expect($remediation->assets->pluck('id')->all())->toBe([$asset->id]);
    expect($remediation->job_description)->toContain('Screen still black after visit.');
});

it('flagging remediation transitions the parent job to RequiresRemediation', function () {
    $store = Store::factory()->create();
    $job   = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->completed()->create();
    $pm    = User::factory()->pm()->create();

    app(JobValidationService::class)->flagRemediation($job, $pm, 'Needs another visit.');

    expect($job->fresh()->job_status)->toBe(JobStatus::RequiresRemediation);
});

it('flagging remediation does not return affected assets to Active', function () {
    $store  = Store::factory()->create();
    $client = Client::find($store->client_id);
    $job    = ServiceJob::factory()->forClient($client, $store)->completed()->create();
    $asset  = Asset::factory()->create([
        'store_id'   => $store->id, 'client_id' => $store->client_id,
        'asset_type' => AssetType::DigitalScreen->value, 'asset_status' => AssetStatus::UnderMaintenance->value,
    ]);
    $job->assets()->attach($asset->id, ['status_before' => 'active']);

    app(JobValidationService::class)->flagRemediation($job, User::factory()->pm()->create(), 'Not resolved.');

    expect($asset->fresh()->asset_status)->toBe(AssetStatus::UnderMaintenance);
});

// ── Hierarchy invariants (US-11.2, reused from EPIC-08) ───────────────────────

it('rejects a second remediation on the same job', function () {
    $store  = Store::factory()->create();
    $client = Client::find($store->client_id);
    $job    = ServiceJob::factory()->forClient($client, $store)->completed()->create();
    $pm     = User::factory()->pm()->create();

    app(JobValidationService::class)->flagRemediation($job, $pm, 'First remediation.');

    // The job itself is now RequiresRemediation, but hasRemediation() checks children —
    // simulate the PM attempting to flag it again (e.g. re-opened via a bug) after the
    // first sub-job already exists.
    expect(fn () => app(JobValidationService::class)->flagRemediation($job->fresh(), $pm, 'Second attempt.'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects flagging remediation on a job that is already a remediation (no Level 3)', function () {
    $store          = Store::factory()->create();
    $client         = Client::find($store->client_id);
    $remediationJob = ServiceJob::factory()->forClient($client, $store)->completed()->create(['job_level' => 2]);
    $pm             = User::factory()->pm()->create();

    expect(fn () => app(JobValidationService::class)->flagRemediation($remediationJob, $pm, 'Trying to go deeper.'))
        ->toThrow(InvalidArgumentException::class);
});

// ── HTTP endpoint ──────────────────────────────────────────────────────────────

it('pm can flag remediation via the HTTP endpoint and is redirected to the new sub-job', function () {
    $store = Store::factory()->create();
    $job   = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->completed()->create();
    $pm    = User::factory()->pm()->create();

    $response = $this->actingAs($pm)
        ->post(route('jobs.flag-remediation', $job), ['reason' => 'Fault persists.']);

    $remediation = ServiceJob::where('parent_job_id', $job->id)->first();
    expect($remediation)->not->toBeNull();

    $response->assertRedirect(route('jobs.show', $remediation));
});

it('technician cannot flag a job for remediation', function () {
    $store = Store::factory()->create();
    $job   = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->completed()->create();
    $tech  = User::factory()->technician()->create();

    $this->actingAs($tech)
        ->post(route('jobs.flag-remediation', $job), ['reason' => 'Fault persists.'])
        ->assertForbidden();
});
