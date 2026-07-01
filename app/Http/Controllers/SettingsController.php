<?php

namespace App\Http\Controllers;

use App\Enums\EarlyStartWindow;
use App\Enums\PlatformSettingKey;
use App\Http\Requests\UpdateSettingsRequest;
use App\Jobs\WriteAuditLog;
use App\Models\PlatformSetting;
use App\Services\Settings\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(PlatformSettings $settings): View
    {
        $this->authorize('viewAny', PlatformSetting::class);

        $values = [];

        foreach (PlatformSettingKey::cases() as $key) {
            $values[$key->value] = $settings->get($key->value, $key->default());
        }

        return view('settings.platform', [
            'settingKeys'       => PlatformSettingKey::cases(),
            'values'            => $values,
            'earlyStartWindows' => EarlyStartWindow::cases(),
        ]);
    }

    public function update(UpdateSettingsRequest $request, PlatformSettings $settings): RedirectResponse
    {
        $validated = $request->validated();

        /** @var array<string, mixed> $newValues */
        $newValues = [
            PlatformSettingKey::SlaAtRiskThresholdPct->value   => (int) $validated['sla_at_risk_threshold_pct'],
            PlatformSettingKey::DefaultEarlyStartWindow->value => $validated['default_early_start_window'],
            PlatformSettingKey::WarrantyAlertDays->value       => array_values(array_map('intval', $validated['warranty_alert_days'])),
            PlatformSettingKey::TechnicianReminderHours->value => (int) $validated['technician_reminder_hours'],
        ];

        $actor = $request->user();

        foreach ($newValues as $key => $newValue) {
            $settingKey = PlatformSettingKey::from($key);
            $oldValue   = $settings->get($key, $settingKey->default());

            if ($oldValue === $newValue) {
                continue;
            }

            $settings->set($key, $newValue);

            WriteAuditLog::dispatch(
                userId: $actor->id,
                userRole: $actor->role->value,
                action: 'settings.updated',
                auditableType: PlatformSetting::class,
                auditableId: (int) PlatformSetting::where('setting_key', $key)->value('id'),
                before: ['key' => $key, 'value' => $oldValue],
                after: ['key' => $key, 'value' => $newValue],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        }

        return redirect()->route('settings.edit')->with('success', 'Settings have been updated.');
    }
}
