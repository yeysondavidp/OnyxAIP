<?php

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\JobStatus;
use App\Enums\PhotoType;
use App\Enums\PostServiceStatus;
use App\Enums\TechnicianJobStatus;
use App\Models\Asset;
use App\Models\Client;
use App\Models\JobAttachment;
use App\Models\JobCheckpoint;
use App\Models\JobPhoto;
use App\Models\ServiceJob;
use App\Models\Store;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\JobFlowService;
use App\Services\JobInvitationService;
use App\Services\TechnicianUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function buildSignedUrl(ServiceJob $job, TechnicianProfile $profile, string $token): string
{
    return URL::temporarySignedRoute(
        'technician.job.overview',
        now()->addHours(72),
        ['job' => $job->id, 'technician_profile_id' => $profile->id, 'token' => $token]
    );
}

function setupJobAndProfile(): array
{
    Mail::fake();
    Storage::fake('local');

    $store  = Store::factory()->create();
    $client = Client::find($store->client_id);
    $job    = ServiceJob::factory()->forClient($client, $store)->create([
        'job_status' => JobStatus::Invited->value,
    ]);
    $profile = TechnicianProfile::factory()->create();

    // Invite — creates the pivot row and token
    app(JobInvitationService::class)->invite($job, $profile, User::factory()->pm()->create());

    $token = DB::table('job_technicians')
        ->where('job_id', $job->id)
        ->where('technician_profile_id', $profile->id)
        ->value('invitation_token');

    return [$job, $profile, (string) $token];
}

// ── Screen 1 — Overview (US-10.1) ────────────────────────────────────────────

it('technician can view Screen 1 with a valid signed URL', function () {
    [$job, $profile, $token] = setupJobAndProfile();

    $signedUrl = buildSignedUrl($job, $profile, $token);

    $this->get($signedUrl)->assertOk()->assertSee($job->job_name);
});

it('expired signed URL renders link-expired page', function () {
    [$job, $profile, $token] = setupJobAndProfile();

    // Create an already-expired signed URL
    $expiredUrl = URL::temporarySignedRoute(
        'technician.job.overview',
        now()->subSecond(),
        ['job' => $job->id, 'technician_profile_id' => $profile->id, 'token' => $token]
    );

    $this->get($expiredUrl)->assertStatus(403);
});

it('Screen 1 shows the job description and PM attachments so the tech can prep before travelling', function () {
    [$job, $profile, $token] = setupJobAndProfile();
    $job->update(['job_description' => 'Replace the faulty HDMI cable on screen 2.']);

    JobAttachment::create([
        'job_id'            => $job->id,
        'original_filename' => 'site-photo.jpg',
        'stored_path'       => 'job-attachments/'.$job->id.'/site-photo.jpg',
        'mime_type'         => 'image/jpeg',
        'file_size'         => 1024,
    ]);

    $signedUrl = buildSignedUrl($job, $profile, $token);

    $this->get($signedUrl)
        ->assertOk()
        ->assertSee('Replace the faulty HDMI cable on screen 2.')
        ->assertSee('site-photo.jpg');
});

it('technician can view a PM attachment via a signed URL without a PM session', function () {
    Storage::fake('local');

    [$job, $profile, $token] = setupJobAndProfile();

    $file = UploadedFile::fake()->image('brief.jpg');
    Storage::disk('local')->putFileAs('job-attachments/'.$job->id, $file, 'brief.jpg');

    $attachment = JobAttachment::create([
        'job_id'            => $job->id,
        'original_filename' => 'brief.jpg',
        'stored_path'       => 'job-attachments/'.$job->id.'/brief.jpg',
        'mime_type'         => 'image/jpeg',
        'file_size'         => $file->getSize(),
    ]);

    $url = URL::temporarySignedRoute(
        'technician.job.attachment',
        now()->addHours(72),
        ['job' => $job->id, 'attachment' => $attachment->id, 'technician_profile_id' => $profile->id, 'token' => $token]
    );

    $this->get($url)->assertOk();
});

it('cannot view an attachment that belongs to a different job via the signed URL', function () {
    Storage::fake('local');

    [$job, $profile, $token] = setupJobAndProfile();
    $otherJob                = ServiceJob::factory()->create();

    $attachment = JobAttachment::create([
        'job_id'            => $otherJob->id,
        'original_filename' => 'other.jpg',
        'stored_path'       => 'job-attachments/'.$otherJob->id.'/other.jpg',
        'mime_type'         => 'image/jpeg',
        'file_size'         => 1024,
    ]);

    $url = URL::temporarySignedRoute(
        'technician.job.attachment',
        now()->addHours(72),
        ['job' => $job->id, 'attachment' => $attachment->id, 'technician_profile_id' => $profile->id, 'token' => $token]
    );

    $this->get($url)->assertNotFound();
});

// ── Cross-checkpoint signed URL continuity (regression) ──────────────────────

it('technician can hop through every checkpoint without the link going invalid', function () {
    Storage::fake('local');

    [$job, $profile, $token] = setupJobAndProfile();

    DB::table('job_technicians')
        ->where('job_id', $job->id)
        ->where('technician_profile_id', $profile->id)
        ->update(['technician_status' => TechnicianJobStatus::Accepted->value]);

    $signedUrl = buildSignedUrl($job, $profile, $token);

    // Screen 1 → Start: POST shares the overview's URI, so the original
    // signature is still valid here even before the fix.
    $startResponse = $this->post($signedUrl, ['gps_status' => 'granted']);
    $startResponse->assertRedirect();
    $beforePhotosUrl = $startResponse->headers->get('Location');

    // Screen 2: a different route path — this is where the stale-signature
    // bug used to surface as "This link isn't valid".
    $this->get($beforePhotosUrl)->assertOk();

    // The rest of the flow re-signs from the current request the same way
    // the Blade views do, via TechnicianUrlService::resignFromRequest().
    $request    = Request::create($beforePhotosUrl);
    $urlService = app(TechnicianUrlService::class);

    $briefUrl = $urlService->resignFromRequest($request, 'technician.job.brief', ['job' => $job->id]);
    $this->get($briefUrl)->assertOk();

    $afterPhotosUrl = $urlService->resignFromRequest($request, 'technician.job.after-photos', ['job' => $job->id]);
    $this->get($afterPhotosUrl)->assertOk();

    // Seed the required after-photo directly — upload idempotency is covered elsewhere.
    $file = UploadedFile::fake()->image('after.jpg', 800, 600);
    app(JobFlowService::class)->storePhoto($job, $profile, PhotoType::After, $file, 'after-flow-continuity');

    $completeUrl      = $urlService->resignFromRequest($request, 'technician.job.complete', ['job' => $job->id]);
    $completeResponse = $this->post($completeUrl, ['gps_status' => 'skipped']);
    $completeResponse->assertRedirect();

    $this->get($completeResponse->headers->get('Location'))->assertOk();
});

// ── Start checkpoint (US-10.2) ────────────────────────────────────────────────

it('start checkpoint records GPS and flips status to started', function () {
    [$job, $profile, $token] = setupJobAndProfile();

    // Accept first so we can start
    DB::table('job_technicians')
        ->where('job_id', $job->id)
        ->where('technician_profile_id', $profile->id)
        ->update(['technician_status' => TechnicianJobStatus::Accepted->value]);

    $signedParams = http_build_query([
        'token'                 => $token,
        'technician_profile_id' => $profile->id,
        // Signed URL params omitted — start is a POST, signed middleware already passed on GET
    ]);

    $service = app(JobFlowService::class);
    $service->startJob($job, $profile, ['lat' => -33.8688, 'lng' => 151.2093, 'gps_status' => 'granted']);

    // Technician pivot updated to Started
    $this->assertDatabaseHas('job_technicians', [
        'job_id'                => $job->id,
        'technician_profile_id' => $profile->id,
        'technician_status'     => 'started',
    ]);

    // Checkpoint record created
    $this->assertDatabaseHas('job_checkpoints', [
        'job_id'                => $job->id,
        'technician_profile_id' => $profile->id,
        'start_gps_status'      => 'granted',
    ]);
});

it('start checkpoint writes start_timestamp_utc and gps data', function () {
    [$job, $profile, $token] = setupJobAndProfile();

    DB::table('job_technicians')
        ->where('job_id', $job->id)
        ->where('technician_profile_id', $profile->id)
        ->update(['technician_status' => TechnicianJobStatus::Accepted->value]);

    app(JobFlowService::class)->startJob($job, $profile, [
        'lat'        => -33.8688,
        'lng'        => 151.2093,
        'gps_status' => 'granted',
    ]);

    $checkpoint = JobCheckpoint::where('job_id', $job->id)
        ->where('technician_profile_id', $profile->id)
        ->first();

    expect($checkpoint)->not->toBeNull();
    expect($checkpoint->start_timestamp_utc)->not->toBeNull();
    expect($checkpoint->start_gps_status)->toBe('granted');
    expect(abs((float) $checkpoint->start_lat - (-33.8688)))->toBeLessThan(0.001);
});

it('start checkpoint rolls job to InProgress', function () {
    [$job, $profile, $token] = setupJobAndProfile();

    DB::table('job_technicians')
        ->where('job_id', $job->id)
        ->where('technician_profile_id', $profile->id)
        ->update(['technician_status' => TechnicianJobStatus::Accepted->value]);

    app(JobFlowService::class)->startJob($job, $profile, ['gps_status' => 'denied']);

    expect($job->fresh()->job_status)->toBe(JobStatus::InProgress);
});

// ── Photo upload (US-10.6 idempotency) ────────────────────────────────────────

it('photo upload is idempotent on the same client_upload_id', function () {
    Storage::fake('local');

    [$job, $profile] = setupJobAndProfile();

    $file = UploadedFile::fake()->image('test.jpg', 800, 600);

    $service = app(JobFlowService::class);
    $id1     = $service->storePhoto($job, $profile, PhotoType::Before, $file, 'client-uuid-1');
    $id2     = $service->storePhoto($job, $profile, PhotoType::Before, $file, 'client-uuid-1'); // same id

    expect($id1)->toBe($id2); // idempotent — same DB record returned
    expect(JobPhoto::where('client_upload_id', 'client-uuid-1')->count())->toBe(1);
});

it('second photo with different client_upload_id creates a new record', function () {
    Storage::fake('local');

    [$job, $profile] = setupJobAndProfile();

    $file = UploadedFile::fake()->image('test.jpg', 800, 600);

    $service = app(JobFlowService::class);
    $id1     = $service->storePhoto($job, $profile, PhotoType::Before, $file, 'uuid-a');
    $id2     = $service->storePhoto($job, $profile, PhotoType::Before, $file, 'uuid-b');

    expect($id1)->not->toBe($id2);
    expect(JobPhoto::where('job_id', $job->id)->count())->toBe(2);
});

// ── Complete checkpoint (US-10.4) ─────────────────────────────────────────────

it('complete checkpoint writes end timestamp and asset outcomes', function () {
    Storage::fake('local');

    [$job, $profile, $token] = setupJobAndProfile();
    $store                   = Store::find($job->store_id);
    $asset                   = Asset::factory()->create([
        'store_id'     => $store->id,
        'client_id'    => $store->client_id,
        'asset_type'   => AssetType::DigitalScreen->value,
        'asset_status' => AssetStatus::UnderMaintenance->value,
    ]);

    // Attach asset to job
    $job->assets()->attach($asset->id);

    // Start the job first
    DB::table('job_technicians')
        ->where('job_id', $job->id)
        ->where('technician_profile_id', $profile->id)
        ->update(['technician_status' => TechnicianJobStatus::Started->value]);

    // Upload an after-photo so the Complete checkpoint passes the min-1 check
    $file = UploadedFile::fake()->image('after.jpg', 800, 600);
    app(JobFlowService::class)->storePhoto($job, $profile, PhotoType::After, $file, 'after-uuid-1');

    app(JobFlowService::class)->completeJob(
        $job,
        $profile,
        ['lat' => -33.87, 'lng' => 151.21, 'gps_status' => 'granted'],
        [['asset_id' => $asset->id, 'post_service_status' => PostServiceStatus::Active->value, 'notes' => 'All good.']],
        'Job completed without issues.'
    );

    // Technician pivot updated to Completed
    $this->assertDatabaseHas('job_technicians', [
        'job_id'                => $job->id,
        'technician_profile_id' => $profile->id,
        'technician_status'     => 'completed',
    ]);

    // Checkpoint updated
    $cp = JobCheckpoint::where('job_id', $job->id)->first();
    expect($cp?->end_timestamp_utc)->not->toBeNull();
    expect($cp?->completion_notes)->toBe('Job completed without issues.');
    expect($cp?->end_gps_status)->toBe('granted');

    // Asset outcome persisted
    $this->assertDatabaseHas('job_asset_outcomes', [
        'job_id'              => $job->id,
        'asset_id'            => $asset->id,
        'post_service_status' => PostServiceStatus::Active->value,
        'technician_notes'    => 'All good.',
    ]);
});

it('complete checkpoint rolls job to Completed when all technicians finish', function () {
    Storage::fake('local');

    [$job, $profile, $token] = setupJobAndProfile();

    DB::table('job_technicians')
        ->where('job_id', $job->id)
        ->where('technician_profile_id', $profile->id)
        ->update(['technician_status' => TechnicianJobStatus::Started->value]);

    // Set job to InProgress (needed for the Completed transition)
    DB::table('service_jobs')
        ->where('id', $job->id)
        ->update(['job_status' => JobStatus::InProgress->value]);

    $file = UploadedFile::fake()->image('after.jpg', 800, 600);
    app(JobFlowService::class)->storePhoto($job, $profile, PhotoType::After, $file, 'after-uuid-2');

    app(JobFlowService::class)->completeJob(
        $job->fresh(),
        $profile,
        ['gps_status' => 'skipped'],
        [],
        null,
    );

    expect($job->fresh()->job_status)->toBe(JobStatus::Completed);
});

// ── Cancel start (US-10.2) ────────────────────────────────────────────────────

it('cancel start reverts technician to accepted and discards before-photos', function () {
    Storage::fake('local');

    [$job, $profile] = setupJobAndProfile();

    DB::table('job_technicians')
        ->where('job_id', $job->id)
        ->where('technician_profile_id', $profile->id)
        ->update(['technician_status' => TechnicianJobStatus::Started->value]);

    // Upload a before-photo
    $file = UploadedFile::fake()->image('before.jpg', 800, 600);
    app(JobFlowService::class)->storePhoto($job, $profile, PhotoType::Before, $file, 'before-uuid-1');

    app(JobFlowService::class)->cancelStart($job, $profile);

    // Reverted to accepted
    $this->assertDatabaseHas('job_technicians', [
        'job_id'                => $job->id,
        'technician_profile_id' => $profile->id,
        'technician_status'     => 'accepted',
    ]);

    // Before-photos discarded
    expect(JobPhoto::where('job_id', $job->id)->where('type', PhotoType::Before->value)->count())->toBe(0);
});
