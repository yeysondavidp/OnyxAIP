<?php

return [
    /*
     * Business hours window used by the SLA clock (§10.2). Evaluated in each
     * store's own IANA timezone (Store::store_timezone), not server time.
     * 24-hour clock: business hours run [start, end).
     */
    'business_hours_start' => (int) env('SLA_BUSINESS_HOURS_START', 8),
    'business_hours_end'   => (int) env('SLA_BUSINESS_HOURS_END', 18),

    /*
     * % of the SLA resolution window elapsed at which a job is flagged
     * at-risk (before it fully breaches). Config-driven per US-12.3 —
     * a future US-16.1 Settings screen will expose this without code changes.
     */
    'at_risk_threshold_pct' => (int) env('SLA_AT_RISK_THRESHOLD_PERCENT', 80),
];
