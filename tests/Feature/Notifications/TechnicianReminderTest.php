<?php

use App\Enums\EarlyStartWindow;
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

function jobWithScheduledTechnician(array $jobOverrides = [], array $pivotOverrides = []): array
{
    $store  = Store::factory()->create();
    $client = Client::find($store->client_id);
    $job    = ServiceJob::factory()->forClient($client, $store)->create(array_merge([
        'job_type'           => JobType::RoutineMaintenance->value,
        'early_start_window' => EarlyStartWindow::Anytime->value,
        'scheduled_date'     => now()->addHours(5)->format('Y-m-d'),
        'scheduled_time'     => now()->addHours(5)->format('H:i:s'),
    ], $jobOverrides));
    $profile = TechnicianProfile::factory()->create();

    DB::table('job_technicians')->insert(array_merge([
        'job_id'                => $job->id,
        'technician_profile_id' => $profile->id,
        'technician_status'     => TechnicianJobStatus::Accepted->value,
        'invitation_token'      => 'live-token-123',
        'token_expires_at'      => now()->addHours(48),
    ], $pivotOverrides));

    return [$job, $profile];
}

it('reminds a technician whose visit is within the configured window', function () {
    Notification::fake();

    [$job, $profile] = jobWithScheduledTechnician();

    $this->artisan('technicians:send-reminders')->assertSuccessful();

    Notification::assertSentOnDemand(TechnicianNotification::class, function (TechnicianNotification $n, array $channels, $notifiable) use ($profile) {
        return $n->slot                    === EmailTemplateSlot::JobReminder
            && $notifiable->routes['mail'] === $profile->email;
    });
    $this->assertDatabaseHas('technician_notification_log', [
        'job_id'                => $job->id,
        'technician_profile_id' => $profile->id,
        'notification_type'     => 'reminder',
    ]);
});

it('does not remind a technician who has already started the job', function () {
    Notification::fake();

    jobWithScheduledTechnician([], ['technician_status' => TechnicianJobStatus::Started->value]);

    $this->artisan('technicians:send-reminders')->assertSuccessful();

    Notification::assertSentOnDemandTimes(TechnicianNotification::class, 0);
});

it('does not remind a technician whose visit is outside the window', function () {
    Notification::fake();

    jobWithScheduledTechnician([
        'scheduled_date' => now()->addDays(5)->format('Y-m-d'),
        'scheduled_time' => now()->addDays(5)->format('H:i:s'),
    ]);

    $this->artisan('technicians:send-reminders')->assertSuccessful();

    Notification::assertSentOnDemandTimes(TechnicianNotification::class, 0);
});

it('is idempotent on a second run', function () {
    Notification::fake();

    [$job, $profile] = jobWithScheduledTechnician();

    $this->artisan('technicians:send-reminders')->assertSuccessful();
    $this->artisan('technicians:send-reminders')->assertSuccessful();

    $this->assertDatabaseCount('technician_notification_log', 1);
});
