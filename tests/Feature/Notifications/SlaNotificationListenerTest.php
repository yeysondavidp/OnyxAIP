<?php

use App\Enums\EmailTemplateSlot;
use App\Models\Client;
use App\Models\ServiceJob;
use App\Models\SlaProfile;
use App\Models\Store;
use App\Models\User;
use App\Notifications\PmNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function slaTrackedJobForNotifications(array $overrides = []): ServiceJob
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

it('notifies pms when a job crosses the sla at-risk threshold', function () {
    Notification::fake();

    $pm  = User::factory()->pm()->create();
    $job = slaTrackedJobForNotifications();

    $this->artisan('sla:recompute')->assertSuccessful();

    Notification::assertSentTo($pm, PmNotification::class, fn ($n) => $n->slot === EmailTemplateSlot::PmSlaWarning);
});

it('notifies pms when a job breaches its sla', function () {
    Notification::fake();

    $pm  = User::factory()->pm()->create();
    $job = slaTrackedJobForNotifications(['sla_resolution_target_at' => now()->subMinute()]);

    $this->artisan('sla:recompute')->assertSuccessful();

    Notification::assertSentTo($pm, PmNotification::class, fn ($n) => $n->slot === EmailTemplateSlot::PmSlaBreached);
});

it('does not re-notify on a second recompute run for the same job', function () {
    Notification::fake();

    $pm = User::factory()->pm()->create();
    slaTrackedJobForNotifications();

    $this->artisan('sla:recompute')->assertSuccessful();
    $this->artisan('sla:recompute')->assertSuccessful();

    Notification::assertSentToTimes($pm, PmNotification::class, 1);
});
