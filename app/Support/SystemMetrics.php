<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Thin wrapper around disk and Redis memory queries.
 * Bound in the container so tests can substitute a fake instance.
 */
class SystemMetrics
{
    /**
     * Percentage of disk space used on the filesystem containing $path.
     * Returns 0.0 if the path does not exist or disk functions fail.
     */
    public function storageDiskUsagePercent(string $path): float
    {
        $total = @disk_total_space($path);
        $free  = @disk_free_space($path);

        if ($total === false || $free === false || $total === 0.0) {
            return 0.0;
        }

        return (($total - $free) / $total) * 100;
    }

    /**
     * Percentage of Redis maxmemory currently used.
     * Returns null when maxmemory is 0 (unlimited) or Redis is unreachable.
     */
    public function redisMemoryUsagePercent(): ?float
    {
        try {
            /** @var array<string, mixed>|false $info */
            $info = Redis::connection()->info('memory');

            if (! is_array($info)) {
                return null;
            }

            $used      = (int) ($info['used_memory'] ?? 0);
            $maxmemory = (int) ($info['maxmemory'] ?? 0);

            if ($maxmemory === 0) {
                return null;
            }

            return ($used / $maxmemory) * 100;
        } catch (\Throwable $e) {
            Log::warning('storage.redis_check_failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
