<?php

return [
    /*
     * Backups themselves are taken by Proxmox (docker/docs/backup-restore.md);
     * this file only configures the storage / Redis alerts (US-00.7).
     */

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
