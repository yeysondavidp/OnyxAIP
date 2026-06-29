<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Convert a UTC datetime to a display string in a given IANA timezone.
 *
 * All datetimes are stored in UTC (config/app.php timezone = UTC).
 * Use this helper before presenting any datetime to a user — pass the
 * job or store's IANA timezone string (e.g. 'Australia/Sydney').
 *
 * Usage:
 *   Tz::display($job->scheduled_at, $job->timezone)
 *   Tz::display($job->scheduled_at, $store->timezone, 'd M Y, g:ia')
 */
class Tz
{
    public static function display(
        \DateTimeInterface|string|null $utc,
        string $timezone,
        string $format = 'd M Y, g:ia T',
    ): string {
        if ($utc === null) {
            return '—';
        }

        return Carbon::parse($utc, 'UTC')
            ->setTimezone($timezone)
            ->format($format);
    }

    public static function date(
        \DateTimeInterface|string|null $utc,
        string $timezone,
    ): string {
        return self::display($utc, $timezone, 'd M Y');
    }

    public static function time(
        \DateTimeInterface|string|null $utc,
        string $timezone,
    ): string {
        return self::display($utc, $timezone, 'g:ia T');
    }
}
