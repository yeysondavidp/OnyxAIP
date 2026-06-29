<?php

namespace Tests\Unit;

use App\Notifications\StorageAlertNotification;
use App\Support\SystemMetrics;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Proves storage:check-thresholds sends alerts when thresholds are exceeded (US-00.7).
 */
class CheckStorageThresholdsTest extends TestCase
{
    private function fakeMetrics(float $disk, ?float $redis): SystemMetrics
    {
        return new class($disk, $redis) extends SystemMetrics
        {
            public function __construct(
                private readonly float $disk,
                private readonly ?float $redis,
            ) {}

            public function storageDiskUsagePercent(string $path): float
            {
                return $this->disk;
            }

            public function redisMemoryUsagePercent(): ?float
            {
                return $this->redis;
            }
        };
    }

    public function test_sends_disk_alert_when_above_threshold(): void
    {
        Notification::fake();
        app()->instance(SystemMetrics::class, $this->fakeMetrics(disk: 92.0, redis: null));
        config([
            'backup.storage_threshold_pct' => 80,
            'backup.redis_threshold_pct'   => 80,
            'backup.alert_email'           => 'ops@onyx.com.au',
        ]);

        $this->artisan('storage:check-thresholds')->assertExitCode(0);

        Notification::assertSentOnDemand(StorageAlertNotification::class);
    }

    public function test_sends_redis_alert_when_above_threshold(): void
    {
        Notification::fake();
        app()->instance(SystemMetrics::class, $this->fakeMetrics(disk: 10.0, redis: 85.0));
        config([
            'backup.storage_threshold_pct' => 80,
            'backup.redis_threshold_pct'   => 80,
            'backup.alert_email'           => 'ops@onyx.com.au',
        ]);

        $this->artisan('storage:check-thresholds')->assertExitCode(0);

        Notification::assertSentOnDemand(StorageAlertNotification::class);
    }

    public function test_no_alert_when_both_within_threshold(): void
    {
        Notification::fake();
        app()->instance(SystemMetrics::class, $this->fakeMetrics(disk: 50.0, redis: 40.0));
        config([
            'backup.storage_threshold_pct' => 80,
            'backup.redis_threshold_pct'   => 80,
            'backup.alert_email'           => 'ops@onyx.com.au',
        ]);

        $this->artisan('storage:check-thresholds')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_suppresses_notification_when_no_alert_email_configured(): void
    {
        Notification::fake();
        app()->instance(SystemMetrics::class, $this->fakeMetrics(disk: 95.0, redis: 95.0));
        config([
            'backup.storage_threshold_pct' => 80,
            'backup.redis_threshold_pct'   => 80,
            'backup.alert_email'           => null,
        ]);

        $this->artisan('storage:check-thresholds')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_skips_redis_check_when_maxmemory_not_set(): void
    {
        Notification::fake();
        // redis returns null = no maxmemory configured
        app()->instance(SystemMetrics::class, $this->fakeMetrics(disk: 10.0, redis: null));
        config([
            'backup.storage_threshold_pct' => 80,
            'backup.redis_threshold_pct'   => 80,
            'backup.alert_email'           => 'ops@onyx.com.au',
        ]);

        $this->artisan('storage:check-thresholds')->assertExitCode(0);

        Notification::assertNothingSent();
    }
}
