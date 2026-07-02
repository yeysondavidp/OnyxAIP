<x-layouts.app title="Settings">

    <x-slot:breadcrumbs>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Settings</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 640px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Platform Settings</h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Platform-wide thresholds consumed by the SLA clock and notifications.</p>
        </div>

        <div style="margin-bottom: var(--space-5);">
            <x-onyx.button href="{{ route('email-templates.index') }}" variant="outline" size="sm">Email templates</x-onyx.button>
        </div>

        <x-onyx.card variant="default" padding="xl">
            <form method="POST" action="{{ route('settings.update') }}" novalidate>
                @csrf
                @method('PATCH')

                <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                    <x-onyx.input
                        name="sla_at_risk_threshold_pct"
                        label="SLA at-risk threshold (%)"
                        type="number"
                        min="1"
                        max="99"
                        :value="old('sla_at_risk_threshold_pct', $values['sla_at_risk_threshold_pct'])"
                        :error="$errors->first('sla_at_risk_threshold_pct')"
                        helper="% of the SLA resolution window elapsed before a fault job is flagged at-risk."
                        required
                    />

                    <x-onyx.divider />

                    <x-onyx.select
                        name="default_early_start_window"
                        label="Default early-start window"
                        :error="$errors->first('default_early_start_window')"
                        helper="Pre-selected early-start window when a PM creates a new service job."
                    >
                        @foreach ($earlyStartWindows as $window)
                            <option value="{{ $window->value }}" @selected(old('default_early_start_window', $values['default_early_start_window']) === $window->value)>
                                {{ $window->label() }}
                            </option>
                        @endforeach
                    </x-onyx.select>

                    <x-onyx.divider />

                    <div class="onyx-field">
                        <label class="onyx-field__label">Warranty-alert lead times (days)</label>
                        <div style="display: flex; gap: var(--space-5); margin-top: var(--space-2);">
                            @foreach ([30, 60, 90] as $days)
                                @php $selected = in_array($days, old('warranty_alert_days', $values['warranty_alert_days']), true); @endphp
                                <label style="display: flex; align-items: center; gap: var(--space-2); font-size: var(--fs-14); color: var(--text-primary); cursor: pointer;">
                                    <input type="checkbox" name="warranty_alert_days[]" value="{{ $days }}" @checked($selected) style="width: 16px; height: 16px; cursor: pointer;">
                                    {{ $days }} days
                                </label>
                            @endforeach
                        </div>
                        @error('warranty_alert_days')
                            <span class="onyx-field__hint onyx-field__hint--error" role="alert">{{ $message }}</span>
                        @else
                            <span class="onyx-field__hint">Select one or more — a PM is warned before an asset's warranty lapses.</span>
                        @enderror
                    </div>

                    <x-onyx.divider />

                    <x-onyx.input
                        name="technician_reminder_hours"
                        label="Technician reminder timing (hours before scheduled time)"
                        type="number"
                        min="1"
                        max="168"
                        :value="old('technician_reminder_hours', $values['technician_reminder_hours'])"
                        :error="$errors->first('technician_reminder_hours')"
                        helper="Hours before a scheduled visit that technicians receive a reminder."
                        required
                    />

                    <x-onyx.divider />

                    <x-onyx.input
                        name="link_expiry_warning_hours"
                        label="Link-expiry warning timing (hours before link expires)"
                        type="number"
                        min="1"
                        max="72"
                        :value="old('link_expiry_warning_hours', $values['link_expiry_warning_hours'])"
                        :error="$errors->first('link_expiry_warning_hours')"
                        helper="Hours before a technician's job link expires that they receive a renewal warning."
                        required
                    />

                    <x-onyx.divider />

                    <div class="onyx-field">
                        <label class="onyx-field__label">Notification preferences</label>
                        <div style="display: flex; flex-direction: column; gap: var(--space-2); margin-top: var(--space-2);">
                            @foreach ($pmNotificationSlots as $slot)
                                @php $checked = is_array(old('enabled_notification_types')) ? in_array($slot->value, old('enabled_notification_types'), true) : ! in_array($slot->value, $disabledSlotValues, true); @endphp
                                <label style="display: flex; align-items: center; gap: var(--space-2); font-size: var(--fs-14); color: var(--text-primary); cursor: pointer;">
                                    <input type="checkbox" name="enabled_notification_types[]" value="{{ $slot->value }}" @checked($checked) style="width: 16px; height: 16px; cursor: pointer;">
                                    {{ $slot->label() }}
                                </label>
                            @endforeach
                        </div>
                        <span class="onyx-field__hint">Untick a type to stop sending it — in-app and email both.</span>
                    </div>

                </div>

                <div style="display: flex; justify-content: flex-end; gap: var(--space-3); margin-top: var(--space-7);">
                    <x-onyx.button type="submit" variant="accent">Save settings</x-onyx.button>
                </div>

            </form>
        </x-onyx.card>
    </div>

</x-layouts.app>
