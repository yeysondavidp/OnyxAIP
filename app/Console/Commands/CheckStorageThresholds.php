<?php

namespace App\Console\Commands;

use App\Notifications\StorageAlertNotification;
use App\Support\SystemMetrics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Daily threshold check: disk usage + Redis memory (US-00.7).
 * Sends a mail alert when either crosses the configured threshold.
 * Scheduled by routes/console.php at 08:00 daily (business hours for visibility).
 */
class CheckStorageThresholds extends Command
{
    protected $signature = 'storage:check-thresholds';

    protected $description = 'Alert when storage disk or Redis memory crosses configured thresholds (US-00.7)';

    public function handle(SystemMetrics $metrics): int
    {
        $alertEmail = config('backup.alert_email');
        $storagePct = (int) config('backup.storage_threshold_pct', 80);
        $redisPct   = (int) config('backup.redis_threshold_pct', 80);
        $email      = is_string($alertEmail) && $alertEmail !== '' ? $alertEmail : null;

        $this->checkDisk($metrics, $storagePct, $email);
        $this->checkRedis($metrics, $redisPct, $email);

        return self::SUCCESS;
    }

    private function checkDisk(SystemMetrics $metrics, int $threshold, ?string $email): void
    {
        $used = $metrics->storageDiskUsagePercent(storage_path('app'));

        $this->line(sprintf('  Disk:  %.1f%% used (alert at %d%%)', $used, $threshold));

        if ($used < $threshold) {
            return;
        }

        $body = sprintf(
            'App storage disk usage is %.1f%% — above the %d%% alert threshold. '
            .'Free up space or resize the volume before uploads start failing.',
            $used,
            $threshold,
        );

        $this->warn($body);
        $this->sendAlert('Storage Disk Almost Full', $body, $email);
    }

    private function checkRedis(SystemMetrics $metrics, int $threshold, ?string $email): void
    {
        $used = $metrics->redisMemoryUsagePercent();

        if ($used === null) {
            $this->line('  Redis: maxmemory not set or unreachable — skipped.');

            return;
        }

        $this->line(sprintf('  Redis: %.1f%% used (alert at %d%%)', $used, $threshold));

        if ($used < $threshold) {
            return;
        }

        $body = sprintf(
            'Redis memory usage is %.1f%% of maxmemory — above the %d%% alert threshold. '
            .'Resize the Redis instance before writes start failing (ADR-002 noeviction policy).',
            $used,
            $threshold,
        );

        $this->warn($body);
        $this->sendAlert('Redis Memory Almost Full', $body, $email);
    }

    private function sendAlert(string $subject, string $body, ?string $email): void
    {
        if ($email === null) {
            $this->warn('  No ALERT_EMAIL configured — notification suppressed.');

            return;
        }

        Notification::route('mail', $email)
            ->notify(new StorageAlertNotification($subject, $body));
    }
}
