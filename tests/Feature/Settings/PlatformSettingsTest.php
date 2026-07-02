<?php

use App\Enums\EarlyStartWindow;
use App\Enums\EmailTemplateSlot;
use App\Enums\JobType;
use App\Enums\PlatformSettingKey;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Client;
use App\Models\ServiceJob;
use App\Models\SlaProfile;
use App\Models\Store;
use App\Models\User;
use App\Services\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function validSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'sla_at_risk_threshold_pct'  => 75,
        'default_early_start_window' => EarlyStartWindow::OneHour->value,
        'warranty_alert_days'        => [30, 90],
        'technician_reminder_hours'  => 12,
        'link_expiry_warning_hours'  => 4,
        'enabled_notification_types' => array_map(fn ($s) => $s->value, UpdateSettingsRequest::PM_NOTIFICATION_SLOTS),
    ], $overrides);
}

it('pm can view the settings screen with defaults pre-filled', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertSee('80'); // default sla_at_risk_threshold_pct
});

it('technician cannot view the settings screen', function () {
    $tech = User::factory()->technician()->create();

    $this->actingAs($tech)
        ->get(route('settings.edit'))
        ->assertForbidden();
});

it('unauthenticated visitor is redirected to login', function () {
    $this->get(route('settings.edit'))->assertRedirect(route('login'));
});

it('pm can save valid settings', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->patch(route('settings.update'), validSettingsPayload())
        ->assertRedirect(route('settings.edit'));

    $settings = app(PlatformSettings::class);

    expect($settings->get(PlatformSettingKey::SlaAtRiskThresholdPct->value))->toBe(75);
    expect($settings->get(PlatformSettingKey::DefaultEarlyStartWindow->value))->toBe(EarlyStartWindow::OneHour->value);
    expect($settings->get(PlatformSettingKey::WarrantyAlertDays->value))->toBe([30, 90]);
    expect($settings->get(PlatformSettingKey::TechnicianReminderHours->value))->toBe(12);
    expect($settings->get(PlatformSettingKey::LinkExpiryWarningHours->value))->toBe(4);
    expect($settings->get(PlatformSettingKey::DisabledNotificationTypes->value))->toBe([]);
});

it('unticking a notification type disables it', function () {
    $pm = User::factory()->pm()->create();

    $enabled = array_values(array_diff(
        array_map(fn ($s) => $s->value, UpdateSettingsRequest::PM_NOTIFICATION_SLOTS),
        [EmailTemplateSlot::PmSlaBreached->value],
    ));

    $this->actingAs($pm)->patch(route('settings.update'), validSettingsPayload([
        'enabled_notification_types' => $enabled,
    ]));

    expect(app(PlatformSettings::class)->get(PlatformSettingKey::DisabledNotificationTypes->value))
        ->toBe([EmailTemplateSlot::PmSlaBreached->value]);
});

it('rejects an sla threshold outside 1-99', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->patch(route('settings.update'), validSettingsPayload(['sla_at_risk_threshold_pct' => 100]))
        ->assertSessionHasErrors('sla_at_risk_threshold_pct');
});

it('rejects warranty_alert_days with no selection', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->patch(route('settings.update'), validSettingsPayload(['warranty_alert_days' => []]))
        ->assertSessionHasErrors('warranty_alert_days');
});

it('rejects a warranty_alert_days value outside the allowed set', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->patch(route('settings.update'), validSettingsPayload(['warranty_alert_days' => [45]]))
        ->assertSessionHasErrors('warranty_alert_days.0');
});

it('rejects technician_reminder_hours outside 1-168', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->patch(route('settings.update'), validSettingsPayload(['technician_reminder_hours' => 200]))
        ->assertSessionHasErrors('technician_reminder_hours');
});

it('technician cannot save settings', function () {
    $tech = User::factory()->technician()->create();

    $this->actingAs($tech)
        ->patch(route('settings.update'), validSettingsPayload())
        ->assertForbidden();
});

it('writes one audit entry per changed key and none for unchanged keys', function () {
    $pm = User::factory()->pm()->create();

    $settingsAuditCount = fn () => DB::table('audit_logs')->where('action', 'settings.updated')->count();

    // First save establishes a baseline (every key changes from its default,
    // except disabled_notification_types — the payload enables everything,
    // which already matches the empty-disabled-list default).
    $this->actingAs($pm)->patch(route('settings.update'), validSettingsPayload());
    expect($settingsAuditCount())->toBe(5);

    // Second save with the exact same values should write zero new entries.
    $this->actingAs($pm)->patch(route('settings.update'), validSettingsPayload());
    expect($settingsAuditCount())->toBe(5);
});

it('sla clock reads the live setting instead of the old hardcoded config value', function () {
    $pm      = User::factory()->pm()->create();
    $profile = SlaProfile::factory()->create(['resolution_hours' => 100]);
    $client  = Client::factory()->create(['sla_profile_id' => $profile->id]);
    $store   = Store::factory()->create(['client_id' => $client->id]);

    // Set the threshold to 50% instead of the 80% default.
    $this->actingAs($pm)->patch(route('settings.update'), validSettingsPayload(['sla_at_risk_threshold_pct' => 50]));

    $this->actingAs($pm)->post(route('jobs.store'), [
        'store_id'           => $store->id,
        'job_reference'      => 'JOB-SETTINGS-001',
        'job_name'           => 'Fault',
        'job_description'    => 'Desc.',
        'job_type'           => JobType::FaultRepair->value,
        'early_start_window' => EarlyStartWindow::Anytime->value,
    ]);

    $created = ServiceJob::where('job_reference', 'JOB-SETTINGS-001')->firstOrFail();

    // At 50%, the at-risk instant should sit roughly halfway (not 80%) between start and target.
    $totalSeconds  = $created->sla_clock_started_at->diffInSeconds($created->sla_resolution_target_at);
    $atRiskSeconds = $created->sla_clock_started_at->diffInSeconds($created->sla_at_risk_at);
    $fraction      = $atRiskSeconds / $totalSeconds;

    expect($fraction)->toBeGreaterThan(0.45)->toBeLessThan(0.55);
});
