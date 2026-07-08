<?php

namespace App\Enums;

/**
 * Deliberately Auckland-only for now (US-03.5) — every NZ store known to the
 * platform today (Sylvia Park, Commercial Bay, Newmarket, Queen St) is in
 * Auckland, and the product decision was to add regions only when a real
 * store needs one rather than pre-populate all 16 official NZ regions.
 */
enum NewZealandRegion: string implements Region
{
    case Auckland = 'AUK';

    public function label(): string
    {
        return match ($this) {
            self::Auckland => 'Auckland',
        };
    }
}
