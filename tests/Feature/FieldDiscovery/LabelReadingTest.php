<?php

use App\Ai\AiManager;
use App\Ai\Drivers\FakeAiClient;
use App\Ai\LabelReader;
use App\Enums\AiMode;
use App\Enums\JobStatus;
use App\Jobs\WriteAuditLog;
use App\Models\Asset;
use App\Models\Client;
use App\Models\ServiceJob;
use App\Models\Store;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\JobInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/** @return array{0: ServiceJob, 1: TechnicianProfile, 2: string, 3: FakeAiClient} */
function labelJob(AiMode $mode = AiMode::LabelCrops, string $reply = '{"manufacturer":"Beat","model":"MIB Media Player Model 19","serial_number":"MIB192600317","mac_address":"00ce39d82a51"}'): array
{
    Mail::fake();
    Storage::fake('local');

    $fake = new FakeAiClient($reply);
    config(['ai.default' => 'fake']);
    app(AiManager::class)->extend('fake', fn () => $fake);

    $client  = Client::factory()->create(['client_code' => 'PAN', 'ai_mode' => $mode]);
    $store   = Store::factory()->create(['client_id' => $client->id]);
    $job     = ServiceJob::factory()->forClient($client, $store)->create(['job_status' => JobStatus::Invited->value]);
    $profile = TechnicianProfile::factory()->create();
    app(JobInvitationService::class)->invite($job, $profile, User::factory()->pm()->create());

    return [$job, $profile, (string) DB::table('job_technicians')->where('job_id', $job->id)->value('invitation_token'), $fake];
}

function readLabel(ServiceJob $job, TechnicianProfile $profile, string $token, array $fields = []): TestResponse
{
    $url = URL::temporarySignedRoute('technician.job.devices.read-label', now()->addHour(), ['job' => $job->id, 'technician_profile_id' => $profile->id, 'token' => $token]);

    return test()->post($url, array_merge([
        'label_photo'        => UploadedFile::fake()->image('label.jpg', 800, 400),
        'asset_type'         => 'media_player',
        'confirm_label_only' => '1',
    ], $fields), ['Accept' => 'application/json']);
}

it('reads a label close-up into checked, normalised suggestions', function () {
    [$job, $profile, $token] = labelJob();

    readLabel($job, $profile, $token)->assertOk()->assertJsonPath('fields', [
        'manufacturer' => 'Beat', 'model' => 'MIB Media Player Model 19', 'serial_number' => 'MIB192600317', 'mac_address' => '00:CE:39:D8:2A:51',
    ]);
});

it('drops values that do not look like the field instead of suggesting them', function () {
    [$job, $profile, $token] = labelJob(reply: '{"manufacturer":"Beat","model":null,"serial_number":"see sticker!!","mac_address":"00:CE:39"}');

    readLabel($job, $profile, $token)->assertOk()->assertJsonPath('fields', ['manufacturer' => 'Beat']);
});

it('sends only a sanitised label crop, audited as label_crop', function () {
    [$job, $profile, $token, $fake] = labelJob();
    Bus::fake([WriteAuditLog::class]);

    readLabel($job, $profile, $token);

    expect([$fake->requests[0]->category->value, str_contains($fake->requests[0]->images[0]->bytes, 'Exif')])->toBe(['label_crop', false]);
    Bus::assertDispatched(WriteAuditLog::class, fn (WriteAuditLog $j) => $j->action === 'ai.completed' && $j->after['category'] === 'label_crop');
});

it('never sends router or network labels', function () {
    [$job, $profile, $token, $fake] = labelJob();

    readLabel($job, $profile, $token, ['asset_type' => 'infrastructure'])->assertStatus(422)->assertJsonPath('error', fn ($e) => str_contains($e, 'passwords'));

    expect($fake->requests)->toBe([]);
});

it('requires the technician to confirm the close-up shows only the label', function () {
    [$job, $profile, $token, $fake] = labelJob();

    readLabel($job, $profile, $token, ['confirm_label_only' => '0'])->assertStatus(422);

    expect($fake->requests)->toBe([]);
});

it('is not offered and refuses when the client has AI off', function () {
    [$job, $profile, $token, $fake] = labelJob(AiMode::Off);
    $brief                          = URL::temporarySignedRoute('technician.job.brief', now()->addHour(), ['job' => $job->id, 'technician_profile_id' => $profile->id, 'token' => $token]);

    $this->get($brief)->assertOk()->assertDontSee('Read label');
    readLabel($job, $profile, $token)->assertStatus(422);
    expect($fake->requests)->toBe([]);
});

it('a provider failure leaves the form for manual entry', function () {
    [$job, $profile, $token] = labelJob();
    app(AiManager::class)->extend('fake', fn () => new FakeAiClient(fail: true));
    app(AiManager::class)->forgetDrivers();

    readLabel($job, $profile, $token)->assertStatus(422)->assertJsonPath('error', fn ($e) => str_contains($e, 'by hand'));
});

it('records which fields came from the photo and which the technician changed', function () {
    [$job, $profile, $token] = labelJob();
    $read                    = readLabel($job, $profile, $token)->json();
    $save                    = URL::temporarySignedRoute('technician.job.devices.store', now()->addHour(), ['job' => $job->id, 'technician_profile_id' => $profile->id, 'token' => $token]);

    $this->post($save, [
        'asset_type'   => 'media_player', 'photo' => UploadedFile::fake()->image('device.jpg'), 'ai_token' => $read['token'],
        'manufacturer' => 'Beat', 'model' => 'MIB 19', 'serial_number' => 'MIB192600317', 'mac_address' => '00:CE:39:D8:2A:51',
    ]);

    expect(Asset::pendingDevices()->sole()->discovery_data['ai_read'])->toBe([
        'fields' => ['manufacturer', 'model', 'serial_number', 'mac_address'], 'changed' => ['model'],
    ]);
});

it('a forged token records no AI provenance', function () {
    expect(LabelReader::provenance(str_repeat('a', 40), ['serial_number' => 'X']))->toBeNull();
});

it('the accuracy command reports correct, wrong and unread fields', function () {
    [$job] = labelJob();
    $image = tempnam(sys_get_temp_dir(), 'acc-');
    imagejpeg(imagecreatetruecolor(400, 200), $image);
    $manifest = tempnam(sys_get_temp_dir(), 'man-');
    file_put_contents($manifest, json_encode([['image' => $image, 'expected' => ['serial_number' => 'MIB192600317', 'model' => 'MIB 19']]]));

    $this->artisan('ai:label-accuracy', ['manifest' => $manifest, '--client' => 'PAN'])
        ->expectsOutputToContain('model: read "MIB Media Player Model 19", expected "MIB 19"')
        ->assertSuccessful();
});
