<?php

namespace App\Enums;

/**
 * Implemented by every country's state/region enum (AustralianState,
 * NewZealandRegion, …) so Store::$state, PublicHolidayProvider and
 * BusinessHoursCalculator can stay country-agnostic (US-03.5). Extends
 * BackedEnum (rather than just declaring ->value manually) so static
 * analysis knows ->value is available on every implementor.
 */
interface Region extends \BackedEnum
{
    public function label(): string;
}
