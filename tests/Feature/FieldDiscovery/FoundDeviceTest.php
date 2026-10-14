<?php

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\JobStatus;
use App\Jobs\WriteAuditLog;
use App\Models\Asset;
use App\Models\AssetHistory;
use App\Models\Client;
use App\Models\JobAttachment;
use App\Models\ServiceJob;
use App\Models\Store;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\JobAssetService;
use App\Services\JobInvitationService;
use App\Services\JobValidationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/** @return array{0: ServiceJob, 1: TechnicianProfile, 2: string} */
function discoveryJob(): array
{
    Mail::fake();
    Storage::fake('local');

    $client  = Client::factory()->create(['client_code' => 'PAN']);
    $store   = Store::factory()->create(['client_id' => $client->id]);
    $job     = ServiceJob::factory()->forClient($client, $store)->create(['job_status' => JobStatus::Invited->value]);
    $profile = TechnicianProfile::factory()->create();
    app(JobInvitationService::class)->invite($job, $profile, User::factory()->pm()->create());
    $token = (string) DB::table('job_technicians')->where('job_id', $job->id)->value('invitation_token');

    return [$job, $profile, $token];
}

function addFoundDevice(ServiceJob $job, TechnicianProfile $profile, string $token, array $fields = []): TestResponse
{
    $url = URL::temporarySignedRoute('technician.job.devices.store', now()->addHour(), ['job' => $job->id, 'technician_profile_id' => $profile->id, 'token' => $token]);

    return test()->post($url, array_merge([
        'asset_type'     => AssetType::MediaPlayer->value,
        'location_notes' => 'Front window, left',
        'serial_number'  => 'MIB192600999',
        'mac_address'    => '00:CE:39:D8:2A:99',
        'photo'          => UploadedFile::fake()->image('label.jpg'),
    ], $fields));
}

// ── US-21.1 — technician registers a device on site ─────────────────────────

it('saves a found device as pending, linked to the job, with the next code and the photo', function () {
    [$job, $profile, $token] = discoveryJob();
    Asset::factory()->create(['client_id' => $job->client_id, 'store_id' => $job->store_id, 'asset_code' => 'PAN-PLY-0003']);

    addFoundDevice($job, $profile, $token)->assertRedirect();

    $device = Asset::pendingDevices()->sole();
    expect([
        $device->asset_code,
        $device->found_on_job_id,
        $device->found_by_technician_profile_id,
        $device->discovery_data,
        Storage::disk('local')->exists((string) $device->discovery_photo_path),
        DB::table('job_assets')->where('job_id', $job->id)->where('asset_id', $device->id)->value('status_before'),
    ])->toBe(['PAN-PLY-0004', $job->id, $profile->id, ['mac_address' => '00:CE:39:D8:2A:99'], true, 'pending_verification']);
});

it('keeps pending devices out of the register until verified', function () {
    [$job, $profile, $token] = discoveryJob();
    addFoundDevice($job, $profile, $token);

    expect([Asset::count(), Asset::pendingDevices()->count()])->toBe([0, 1]);
});

it('warns about a possible duplicate instead of saving it', function () {
    [$job, $profile, $token] = discoveryJob();
    Asset::factory()->create(['client_id' => $job->client_id, 'store_id' => $job->store_id, 'asset_code' => 'PAN-PLY-0001', 'serial_number' => 'MIB192600999']);

    addFoundDevice($job, $profile, $token)->assertSessionHas('possible_duplicate.asset_code', 'PAN-PLY-0001');

    expect(Asset::pendingDevices()->count())->toBe(0);
});

it('saves it as new when the technician confirms it is a different device', function () {
    [$job, $profile, $token] = discoveryJob();
    Asset::factory()->create(['client_id' => $job->client_id, 'store_id' => $job->store_id, 'serial_number' => 'MIB192600999']);

    addFoundDevice($job, $profile, $token, ['confirm_new' => '1']);

    expect(Asset::pendingDevices()->count())->toBe(1);
});

it('links an already-registered device at the same store to the visit', function () {
    [$job, $profile, $token] = discoveryJob();
    $existing                = Asset::factory()->create(['client_id' => $job->client_id, 'store_id' => $job->store_id]);
    $url                     = URL::temporarySignedRoute('technician.job.devices.link', now()->addHour(), ['job' => $job->id, 'asset' => $existing->id, 'technician_profile_id' => $profile->id, 'token' => $token]);

    $this->post($url)->assertRedirect();

    expect($job->assets()->pluck('assets.id')->all())->toBe([$existing->id]);
});

it('requires a photo and a device type', function () {
    [$job, $profile, $token] = discoveryJob();

    addFoundDevice($job, $profile, $token, ['photo' => null, 'asset_type' => null])->assertSessionHasErrors(['photo', 'asset_type']);
});

it('refuses a link with the wrong token', function () {
    [$job, $profile] = discoveryJob();

    addFoundDevice($job, $profile, 'not-the-token');

    expect(Asset::pendingDevices()->count())->toBe(0);
});

// ── US-21.3 — PM verification queue ─────────────────────────────────────────

function pendingDevice(string $type = 'digital_screen'): Asset
{
    [$job, $profile, $token] = discoveryJob();
    addFoundDevice($job, $profile, $token, ['asset_type' => $type, 'serial_number' => null, 'mac_address' => null, 'screen_size_inches' => '85', 'orientation' => 'portrait']);

    return Asset::pendingDevices()->sole();
}

it('shows pending devices to a pm and not to a technician', function () {
    $device = pendingDevice();

    $this->actingAs(User::factory()->pm()->create())->get(route('found-devices.index'))->assertOk()->assertSee($device->asset_code);
    $this->actingAs(User::factory()->technician()->create())->get(route('found-devices.index'))->assertForbidden();
});

it('verifying applies the full asset form rules', function () {
    $device = pendingDevice();

    $this->actingAs(User::factory()->pm()->create())
        ->put(route('found-devices.verify', $device), ['asset_code' => $device->asset_code, 'asset_name' => 'Front window screen', 'manufacturer' => 'Samsung', 'model' => 'QM85C', 'status' => 'active'])
        ->assertSessionHasErrors(['resolution_width', 'mount_type']);
});

it('verifying puts the device in the register with its details and the pm as actor', function () {
    $device = pendingDevice();
    $pm     = User::factory()->pm()->create();

    $this->actingAs($pm)->put(route('found-devices.verify', $device), [
        'asset_code'         => $device->asset_code, 'asset_name' => 'Front window screen', 'manufacturer' => 'Samsung', 'model' => 'QM85C',
        'screen_size_inches' => 85, 'resolution_width' => 1080, 'resolution_height' => 1920, 'orientation' => 'portrait',
        'mount_type'         => 'Floor Totem', 'totem_supplied_by' => 'client', 'status' => 'active',
    ])->assertRedirect(route('assets.show', $device->id));

    $verified = Asset::with('screenDetail')->findOrFail($device->id);
    expect([
        $verified->asset_status, $verified->verified_by_user_id, $verified->screenDetail?->resolution_height,
        AssetHistory::where('asset_id', $device->id)->latest('id')->value('actor_id'),
    ])->toBe([AssetStatus::Active, $pm->id, 1920, $pm->id]);
});

it('rejecting needs a reason and removes the device and its photo', function () {
    $device = pendingDevice();
    $pm     = User::factory()->pm()->create();
    Bus::fake([WriteAuditLog::class]);

    $this->actingAs($pm)->post(route('found-devices.reject', $device), [])->assertSessionHasErrors('reason');
    $this->actingAs($pm)->post(route('found-devices.reject', $device), ['reason' => 'Not ONYX equipment'])->assertRedirect(route('found-devices.index'));

    expect([Asset::pendingDevices()->count(), Storage::disk('local')->exists((string) $device->discovery_photo_path)])->toBe([0, false]);
    Bus::assertDispatched(WriteAuditLog::class, fn (WriteAuditLog $job) => $job->action === 'device.rejected' && $job->after['reason'] === 'Not ONYX equipment');
});

it('merging adds the existing device to the visit and keeps the photo on the job', function () {
    $device   = pendingDevice('media_player');
    $existing = Asset::factory()->create(['client_id' => $device->client_id, 'store_id' => $device->store_id, 'asset_code' => 'PAN-PLY-0100']);

    $this->actingAs(User::factory()->pm()->create())->post(route('found-devices.merge', $device), ['into_asset_code' => 'PAN-PLY-0100'])->assertRedirect();

    $job = ServiceJob::findOrFail($device->found_on_job_id);
    expect([
        Asset::pendingDevices()->count(),
        $job->assets()->pluck('assets.id')->all(),
        JobAttachment::where('job_id', $job->id)->count(),
    ])->toBe([0, [$existing->id], 1]);
});

// ── Guards ──────────────────────────────────────────────────────────────────

it('a job cannot be validated while devices found on it are unverified', function () {
    $device = pendingDevice();
    $job    = ServiceJob::findOrFail($device->found_on_job_id);
    $job->forceFill(['job_status' => JobStatus::Completed->value])->save();

    app(JobValidationService::class)->validate($job, User::factory()->pm()->create());
})->throws(InvalidArgumentException::class, 'Verify the 1 device(s)');

it('saving the job form does not detach devices found on site', function () {
    $device = pendingDevice();
    $job    = ServiceJob::findOrFail($device->found_on_job_id);

    app(JobAssetService::class)->sync($job, []);

    expect(DB::table('job_assets')->where('job_id', $job->id)->where('asset_id', $device->id)->exists())->toBeTrue();
});

it('nobody can set pending verification through the asset form', function () {
    $store = Store::factory()->create();

    $this->actingAs(User::factory()->pm()->create())->post(route('assets.store'), [
        'asset_code'          => 'X-1', 'asset_type' => 'lightbox', 'client_id' => $store->client_id, 'store_id' => $store->id,
        'asset_name'          => 'x', 'manufacturer' => 'x', 'model' => 'x', 'asset_status' => 'pending_verification',
        'lightbox_dimensions' => '1x1x1', 'light_type' => 'led', 'content_change_frequency' => 'static',
    ])->assertSessionHasErrors('asset_status');
});

it('the database refuses an unknown asset status', function () {
    DB::table('assets')->where('id', Asset::factory()->create()->id)->update(['asset_status' => 'lost']);
})->throws(QueryException::class);

// ── Pages render ────────────────────────────────────────────────────────────

it('the technician brief offers adding a device and lists the ones already added', function () {
    [$job, $profile, $token] = discoveryJob();
    addFoundDevice($job, $profile, $token);
    $brief = URL::temporarySignedRoute('technician.job.brief', now()->addHour(), ['job' => $job->id, 'technician_profile_id' => $profile->id, 'token' => $token]);

    $this->get($brief)->assertOk()->assertSee('Add a device I found')->assertSee('PAN-PLY-0001')->assertSee('Waiting for PM');
});

it('the verification page renders for every device type', function (string $type) {
    $device = pendingDevice($type);

    $this->actingAs(User::factory()->pm()->create())->get(route('found-devices.show', $device))->assertOk()->assertSee('Verify and add to register');
})->with(['digital_screen', 'media_player', 'lightbox', 'infrastructure', 'window_fixture']);

it('the dashboard tells the pm there are devices to verify', function () {
    pendingDevice();

    $this->actingAs(User::factory()->pm()->create())->get(route('dashboard'))->assertOk()->assertSee('1 device to verify');
});
