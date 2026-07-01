<?php

namespace App\Enums;

/**
 * Single source of truth for every PM-editable platform setting (US-16.1).
 * EPIC-12/13 read these via App\Services\Settings\PlatformSettings — never
 * the `platform_settings` table directly, and never a hardcoded constant.
 */
enum PlatformSettingKey: string
{
    case SlaAtRiskThresholdPct   = 'sla_at_risk_threshold_pct';
    case DefaultEarlyStartWindow = 'default_early_start_window';
    case WarrantyAlertDays       = 'warranty_alert_days';
    case TechnicianReminderHours = 'technician_reminder_hours';

    public function label(): string
    {
        return match ($this) {
            self::SlaAtRiskThresholdPct   => 'SLA at-risk threshold',
            self::DefaultEarlyStartWindow => 'Default early-start window',
            self::WarrantyAlertDays       => 'Warranty-alert lead times',
            self::TechnicianReminderHours => 'Technician reminder timing',
        };
    }

    public function helperText(): string
    {
        return match ($this) {
            self::SlaAtRiskThresholdPct   => '% of the SLA resolution window elapsed before a fault job is flagged at-risk.',
            self::DefaultEarlyStartWindow => 'Pre-selected early-start window when a PM creates a new service job.',
            self::WarrantyAlertDays       => 'Days before warranty expiry to warn a PM — select one or more.',
            self::TechnicianReminderHours => 'Hours before a scheduled visit that technicians receive a reminder.',
        };
    }

    /** Default used before a PM has ever saved this setting. */
    public function default(): mixed
    {
        return match ($this) {
            self::SlaAtRiskThresholdPct   => 80,
            self::DefaultEarlyStartWindow => EarlyStartWindow::Anytime->value,
            self::WarrantyAlertDays       => [30, 60, 90],
            self::TechnicianReminderHours => 24,
        };
    }
}
