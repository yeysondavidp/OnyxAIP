<?php

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\JobStatus;
use App\Enums\PhotoType;
use App\Enums\PostServiceStatus;
use App\Models\Asset;
use App\Models\Client;
use App\Models\JobAssetOutcome;
use App\Models\JobPhoto;
use App\Models\ServiceHistory;
use App\Models\ServiceJob;
use App\Models\Store;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\JobValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Builds a Completed job with one Under Maintenance asset attached
 * (status_before captured as it would be at real job creation, US-11.1).
 */
function makeCompletedJobWithAsset(string $statusBefore = 'active'): array
{
    $store  = Store::factory()->create();
    $client = Client::find($store->client_id);
    $job    = ServiceJob::factory()->forClient($client, $store)->completed()->create();
    $asset  = Asset::factory()->create([
        'store_id'     => $store->id,
        'client_id'    => $store->client_id,
        'asset_type'   => AssetType::DigitalScreen->value,
        'asset_status' => AssetStatus::UnderMaintenance->value,
    ]);

    $job->assets()->attach($asset->id, ['status_before' => $statusBefore]);

    return [$job, $asset];
}

// ── Default outcome (US-11.1) ─────────────────────────────────────────────────

it('validate returns an asset to Active by default when no outcome or override exists', function () {
    [$job, $asset] = makeCompletedJobWithAsset();
    $pm            = User::factory()->pm()->create();

    app(JobValidationService::class)->validate($job, $pm);

    expect($asset->fresh()->asset_status)->toBe(AssetStatus::Active);
    expect($job->fresh()->job_status)->toBe(JobStatus::Validated);
});

it('validate writes one append-only service_history row per affected asset', function () {
    [$job, $asset] = makeCompletedJobWithAsset('faulty');
    $pm            = User::factory()->pm()->create();

    app(JobValidationService::class)->validate($job, $pm);

    $history = ServiceHistory::where('asset_id', $asset->id)->where('service_job_id', $job->id)->first();

    expect($history)->not->toBeNull();
    expect($history->status_before)->toBe(AssetStatus::Faulty);
    expect($history->status_after)->toBe(AssetStatus::Active);
    expect($history->job_type)->toBe($job->job_type);
});

it('validate uses the technician Screen-4 outcome as the default when no PM override is given', function () {
    [$job, $asset] = makeCompletedJobWithAsset();

    JobAssetOutcome::create([
        'job_id'              => $job->id,
        'asset_id'            => $asset->id,
        'post_service_status' => PostServiceStatus::StillFaulty->value,
        'technician_notes'    => 'Panel still dead after replacement.',
    ]);

    $pm = User::factory()->pm()->create();
    app(JobValidationService::class)->validate($job, $pm);

    expect($asset->fresh()->asset_status)->toBe(AssetStatus::Faulty);

    $history = ServiceHistory::where('asset_id', $asset->id)->first();
    expect($history->status_after)->toBe(AssetStatus::Faulty);
    expect($history->technician_notes)->toBe('Panel still dead after replacement.');
});

it('a PM override wins over the technician Screen-4 outcome', function () {
    [$job, $asset] = makeCompletedJobWithAsset();

    JobAssetOutcome::create([
        'job_id'              => $job->id,
        'asset_id'            => $asset->id,
        'post_service_status' => PostServiceStatus::StillFaulty->value,
    ]);

    $pm = User::factory()->pm()->create();
    app(JobValidationService::class)->validate($job, $pm, [$asset->id => PostServiceStatus::Decommissioned->value]);

    expect($asset->fresh()->asset_status)->toBe(AssetStatus::Decommissioned);
});

it('replaced outcome decommissions the original asset record', function () {
    [$job, $asset] = makeCompletedJobWithAsset();
    $pm            = User::factory()->pm()->create();

    app(JobValidationService::class)->validate($job, $pm, [$asset->id => PostServiceStatus::Replaced->value]);

    expect($asset->fresh()->asset_status)->toBe(AssetStatus::Decommissioned);
});

// ── Photos captured on the history row ────────────────────────────────────────

it('validate captures before and after photo paths on the history row', function () {
    [$job, $asset] = makeCompletedJobWithAsset();
    $profile       = TechnicianProfile::factory()->create();

    JobPhoto::create([
        'job_id'      => $job->id, 'technician_profile_id' => $profile->id, 'type' => PhotoType::Before->value,
        'stored_path' => 'job-photos/before-1.jpg', 'mime_type' => 'image/jpeg', 'client_upload_id' => 'u1',
    ]);
    JobPhoto::create([
        'job_id'      => $job->id, 'technician_profile_id' => $profile->id, 'type' => PhotoType::After->value,
        'stored_path' => 'job-photos/after-1.jpg', 'mime_type' => 'image/jpeg', 'client_upload_id' => 'u2',
    ]);

    $pm = User::factory()->pm()->create();
    app(JobValidationService::class)->validate($job, $pm);

    $history = ServiceHistory::where('asset_id', $asset->id)->first();
    expect($history->before_photo_paths)->toContain('job-photos/before-1.jpg');
    expect($history->after_photo_paths)->toContain('job-photos/after-1.jpg');
});

// ── Illegal state / scope guards ──────────────────────────────────────────────

it('validate rejects a job that is not Completed', function () {
    $store = Store::factory()->create();
    $job   = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->draft()->create();
    $pm    = User::factory()->pm()->create();

    expect(fn () => app(JobValidationService::class)->validate($job, $pm))
        ->toThrow(InvalidArgumentException::class);
});

it('validate rejects an asset belonging to a different client than the job (defence in depth)', function () {
    [$job]        = makeCompletedJobWithAsset();
    $foreignAsset = Asset::factory()->create(); // different client/store entirely

    DB::table('job_assets')->insert([
        'job_id' => $job->id, 'asset_id' => $foreignAsset->id, 'status_before' => 'active',
    ]);

    $pm = User::factory()->pm()->create();

    expect(fn () => app(JobValidationService::class)->validate($job, $pm))
        ->toThrow(InvalidArgumentException::class);
});

// ── HTTP endpoint ──────────────────────────────────────────────────────────────

it('pm can validate a completed job via the HTTP endpoint with an override', function () {
    [$job, $asset] = makeCompletedJobWithAsset();
    $pm            = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->post(route('jobs.validate', $job), [
            'decisions' => [$asset->id => PostServiceStatus::Decommissioned->value],
        ])
        ->assertRedirect(route('jobs.show', $job));

    expect($asset->fresh()->asset_status)->toBe(AssetStatus::Decommissioned);
    expect($job->fresh()->job_status)->toBe(JobStatus::Validated);
});

it('technician cannot access the validate endpoint', function () {
    [$job] = makeCompletedJobWithAsset();
    $tech  = User::factory()->technician()->create();

    $this->actingAs($tech)
        ->post(route('jobs.validate', $job))
        ->assertForbidden();
});

it('pm can open the validation review page for a completed job', function () {
    [$job] = makeCompletedJobWithAsset();
    $pm    = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->get(route('jobs.validate-form', $job))
        ->assertOk()
        ->assertSee('Review');
});

// ── Append-only enforcement (US-11.3) ─────────────────────────────────────────

it('service_history record cannot be updated', function () {
    [$job, $asset] = makeCompletedJobWithAsset();
    app(JobValidationService::class)->validate($job, User::factory()->pm()->create());

    $history                   = ServiceHistory::where('asset_id', $asset->id)->firstOrFail();
    $history->technician_notes = 'tampered';
    $result                    = $history->save();

    expect($result)->toBeFalse();
    expect(ServiceHistory::find($history->id)->technician_notes)->not->toBe('tampered');
});

it('service_history record cannot be deleted', function () {
    [$job, $asset] = makeCompletedJobWithAsset();
    app(JobValidationService::class)->validate($job, User::factory()->pm()->create());

    $history = ServiceHistory::where('asset_id', $asset->id)->firstOrFail();
    $result  = $history->delete();

    expect($result)->toBeFalsy();
    expect(ServiceHistory::find($history->id))->not->toBeNull();
});
