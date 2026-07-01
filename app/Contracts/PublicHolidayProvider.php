<?php

namespace App\Contracts;

use App\Enums\AustralianState;
use Carbon\CarbonInterface;

/**
 * Source of truth for whether a given calendar date is a public holiday in a
 * given Australian state (SRA §10.2, §17). Bound in AppServiceProvider so the
 * default static/rule-based implementation can be swapped for a licensed data
 * source later — config-only swap, no BusinessHoursCalculator changes (US-12.2).
 */
interface PublicHolidayProvider
{
    /** $date is compared as a local calendar day — pass it already in the store's timezone. */
    public function isHoliday(CarbonInterface $date, AustralianState $state): bool;
}
