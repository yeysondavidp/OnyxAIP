<?php

namespace App\Contracts;

use App\Enums\Region;
use Carbon\CarbonInterface;

/**
 * Source of truth for whether a given calendar date is a public holiday in a
 * given state/region (SRA §10.2, §17). Bound in AppServiceProvider so the
 * default static/rule-based implementation can be swapped for a licensed data
 * source later — config-only swap, no BusinessHoursCalculator changes (US-12.2).
 *
 * Typed against the country-agnostic Region interface (US-03.5) — a store's
 * region can be an AustralianState or a NewZealandRegion; implementations
 * that don't yet have a calendar for a given Region must degrade gracefully
 * (see StaticAuPublicHolidayProvider) rather than throwing.
 */
interface PublicHolidayProvider
{
    /** $date is compared as a local calendar day — pass it already in the store's timezone. */
    public function isHoliday(CarbonInterface $date, Region $state): bool;
}
