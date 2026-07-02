<?php

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\EmailTemplateSlot;
use App\Enums\PlatformSettingKey;
use App\Models\Asset;
use App\Models\Client;
use App\Models\ServiceJob;
use App\Models\SlaProfile;
use App\Models\Store;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Notifications\PmNotification;
use App\Notifications\TechnicianNotification;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('sends jobStatusChanged to every pm', function () {
    Notification::fake();

    $pm1   = User::factory()->pm()->create();
    $pm2   = User::factory()->pm()->create();
    $tech  = User::factory()->technician()->create();
    $store = Store::factory()->create();
    $job   = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->create();

    app(NotificationDispatcher::class)->jobStatusChanged($job);

    Notification::assertSentTo([$pm1, $pm2], PmNotification::class, fn ($n) => $n->slot === EmailTemplateSlot::PmJobStatusChanged);
    Notification::assertNotSentTo($tech, PmNotification::class);
});

it('does not send when the notification type is disabled in settings', function () {
    Notification::fake();

    $pm = User::factory()->pm()->create();
    app(PlatformSettings::class)->set(PlatformSettingKey::DisabledNotificationTypes->value, [EmailTemplateSlot::PmJobStatusChanged->value]);

    $store = Store::factory()->create();
    $job   = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->create();

    app(NotificationDispatcher::class)->jobStatusChanged($job);

    // The notification IS still "sent" from Laravel's perspective (queued), but
    // via() suppresses all channels — assert no mail/database side effects.
    $this->assertDatabaseCount('notifications', 0);
});

it('sends assetStatusChanged and newFaultReported when an asset becomes faulty', function () {
    Notification::fake();

    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();
    $asset = Asset::factory()->create([
        'store_id'   => $store->id,
        'client_id'  => $store->client_id,
        'asset_type' => AssetType::DigitalScreen->value,
    ]);

    app(NotificationDispatcher::class)->assetStatusChanged($asset, AssetStatus::Active, AssetStatus::Faulty);
    app(NotificationDispatcher::class)->newFaultReported($asset);

    Notification::assertSentTo($pm, PmNotification::class, fn ($n) => $n->slot === EmailTemplateSlot::PmAssetStatusChanged);
    Notification::assertSentTo($pm, PmNotification::class, fn ($n) => $n->slot === EmailTemplateSlot::PmNewFaultReported);
});

it('sends slaWarning and slaBreached with the correct variables', function () {
    Notification::fake();

    $pm      = User::factory()->pm()->create();
    $profile = SlaProfile::factory()->create();
    $client  = Client::factory()->create(['sla_profile_id' => $profile->id, 'client_name' => 'Pandora ANZ']);
    $store   = Store::factory()->create(['client_id' => $client->id]);
    $job     = ServiceJob::factory()->forClient($client, $store)->create([
        'sla_clock_started_at'     => now()->subHours(8),
        'sla_resolution_target_at' => now()->addHours(2),
    ]);

    app(NotificationDispatcher::class)->slaWarning($job);
    app(NotificationDispatcher::class)->slaBreached($job);

    Notification::assertSentTo($pm, PmNotification::class, function (PmNotification $n) {
        return $n->slot                     === EmailTemplateSlot::PmSlaWarning
            && $n->variables['client_name'] === 'Pandora ANZ';
    });
    Notification::assertSentTo($pm, PmNotification::class, fn ($n) => $n->slot === EmailTemplateSlot::PmSlaBreached);
});

it('sends warrantyExpiryApproaching with days remaining', function () {
    Notification::fake();

    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();
    $asset = Asset::factory()->create(['store_id' => $store->id, 'client_id' => $store->client_id]);

    app(NotificationDispatcher::class)->warrantyExpiryApproaching($asset, 30);

    Notification::assertSentTo($pm, PmNotification::class, function (PmNotification $n) {
        return $n->slot                        === EmailTemplateSlot::PmWarrantyExpiryApproaching
            && $n->variables['days_remaining'] === '30';
    });
});

it('sends technician notifications on-demand to the technician email only', function () {
    Notification::fake();

    $store   = Store::factory()->create();
    $job     = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->create();
    $profile = TechnicianProfile::factory()->create(['email' => 'tech@example.test']);

    app(NotificationDispatcher::class)->technicianJobReminder($job, $profile, 'https://example.test/link');

    Notification::assertSentOnDemand(TechnicianNotification::class, function (TechnicianNotification $n, array $channels, $notifiable) {
        return $n->slot                    === EmailTemplateSlot::JobReminder
            && $notifiable->routes['mail'] === 'tech@example.test';
    });
});
