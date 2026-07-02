<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Shared duration math reused across reports and scheduled checks — a single
 * place for "hours between two UTC timestamps" and "days until a date" so
 * neither is recomputed inline per call site (Engineering Bar — Clean).
 */
class DurationCalculator
{
    public static function hours(Carbon $start, Carbon $end): float
    {
        return round($start->diffInMinutes($end) / 60, 2);
    }

    /** Negative once $target is in the past. */
    public static function daysUntil(Carbon $today, Carbon $target): int
    {
        return (int) $today->startOfDay()->diffInDays($target, false);
    }
}
