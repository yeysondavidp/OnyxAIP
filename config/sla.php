<?php

return [
    /*
     * Business hours window used by the SLA clock (§10.2). Evaluated in each
     * store's own IANA timezone (Store::store_timezone), not server time.
     * 24-hour clock: business hours run [start, end).
     */
    'business_hours_start' => (int) env('SLA_BUSINESS_HOURS_START', 8),
    'business_hours_end'   => (int) env('SLA_BUSINESS_HOURS_END', 18),

    // At-risk threshold % moved to the platform_settings table (US-16.1) —
    // see App\Enums\PlatformSettingKey::SlaAtRiskThresholdPct.
];
