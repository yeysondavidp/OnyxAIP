<?php

use App\Enums\EmailTemplateSlot;
use App\Enums\JobType;
use App\Enums\TechnicianJobStatus;
use App\Models\Client;
use App\Models\ServiceJob;
use App\Models\Store;
use App\Models\TechnicianProfile;
use App\Notifications\TechnicianNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function jobWithExpiringToken(array $pivotOverrides = []): array
{
    $store   = Store::factory()->create();
    $client  = Client::find($store->client_id);
    $job     = ServiceJob::factory()->forClient($client, $store)->create(['job_type' => JobType::Survey->value]);
    $profile = TechnicianProfile::factory()->create();

    DB::table('job_technicians')->insert(array_merge([
        'job_id'                => $job->id,
        'technician_profile_id' => $profile->id,
        'technician_status'     => TechnicianJobStatus::Accepted->value,
        'invitation_token'      => 'about-to-expire-token',
        'token_expires_at'      => now()->addHours(3),
    ], $pivotOverrides));

    return [$job, $profile];
}

it('warns a technician and re-issues a fresh token within the configured window', function () {
    Notification::fake();

    [$job, $profile] = jobWithExpiringToken();
    $originalToken   = DB::table('job_technicians')
        ->where('job_id', $job->id)->where('technician_profile_id', $profile->id)
        ->value('invitation_token');

    $this->artisan('technicians:send-link-expiry-warnings')->assertSuccessful();

    Notification::assertSentOnDemand(TechnicianNotification::class, function (TechnicianNotification $n, array $channels, $notifiable) use ($profile) {
        return $n->slot                    === EmailTemplateSlot::LinkExpiryWarning
            && $notifiable->routes['mail'] === $profile->email;
    });

    $newToken = DB::table('job_technicians')
        ->where('job_id', $job->id)->where('technician_profile_id', $profile->id)
        ->value('invitation_token');

    expect($newToken)->not->toBe($originalToken);
});

it('does not warn when the token is outside the configured window', function () {
    Notification::fake();

    jobWithExpiringToken(['token_expires_at' => now()->addHours(48)]);

    $this->artisan('technicians:send-link-expiry-warnings')->assertSuccessful();

    Notification::assertSentOnDemandTimes(TechnicianNotification::class, 0);
});

it('does not warn a technician who has already completed the job', function () {
    Notification::fake();

    jobWithExpiringToken(['technician_status' => TechnicianJobStatus::Completed->value]);

    $this->artisan('technicians:send-link-expiry-warnings')->assertSuccessful();

    Notification::assertSentOnDemandTimes(TechnicianNotification::class, 0);
});

it('is idempotent on a second run', function () {
    Notification::fake();

    [$job, $profile] = jobWithExpiringToken();

    $this->artisan('technicians:send-link-expiry-warnings')->assertSuccessful();
    $this->artisan('technicians:send-link-expiry-warnings')->assertSuccessful();

    $this->assertDatabaseCount('technician_notification_log', 1);
});
