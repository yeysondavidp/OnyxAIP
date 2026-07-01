<?php

namespace App\Services\Settings;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The only door onto the platform_settings table (US-16.1) — EPIC-12/13 must call
 * get() rather than querying the table directly, so there is exactly one place
 * that knows how a setting is stored and cached.
 */
class PlatformSettings
{
    private const CACHE_TTL_SECONDS = 60;

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember($this->cacheKey($key), self::CACHE_TTL_SECONDS, function () use ($key, $default) {
            $raw = DB::table('platform_settings')->where('setting_key', $key)->value('value');

            return $raw === null ? $default : json_decode($raw, true);
        });
    }

    public function set(string $key, mixed $value): void
    {
        $exists = DB::table('platform_settings')->where('setting_key', $key)->exists();

        DB::table('platform_settings')->updateOrInsert(
            ['setting_key' => $key],
            array_filter([
                'value'      => json_encode($value),
                'updated_at' => now(),
                'created_at' => $exists ? null : now(),
            ], fn ($v) => $v !== null),
        );

        Cache::forget($this->cacheKey($key));
    }

    private function cacheKey(string $key): string
    {
        return "platform_settings.{$key}";
    }
}
