<?php

return [
    /*
     * Absolute path for daily backup directories.
     * In production, mount a separate host path here so backups survive
     * a container rebuild: e.g. /data/backups/onyx-aip
     * Defaults to storage/backups (same volume as app storage — useful for
     * quick restores but not a substitute for off-site copies).
     */
    'path' => env('BACKUP_PATH', storage_path('backups')),

    /*
     * Number of daily backup directories to retain before pruning.
     */
    'retain_days' => (int) env('BACKUP_RETAIN_DAYS', 7),

    /*
     * Email address to receive storage / Redis memory alert notifications.
     * Leave blank to suppress alerts (useful in local dev).
     * Ties into the §12 notification infra when it is built.
     */
    'alert_email' => env('ALERT_EMAIL'),

    /*
     * Disk usage % at which a storage alert is sent.
     * Measured against the filesystem that hosts storage/app.
     */
    'storage_threshold_pct' => (int) env('STORAGE_ALERT_THRESHOLD_PERCENT', 80),

    /*
     * Redis used_memory % of maxmemory at which an alert is sent.
     * Only relevant when Redis runs with a maxmemory limit (ADR-002: noeviction).
     */
    'redis_threshold_pct' => (int) env('REDIS_ALERT_THRESHOLD_PERCENT', 80),
];
