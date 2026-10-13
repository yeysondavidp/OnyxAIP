<?php

namespace App\Enums;

enum PostServiceStatus: string
{
    case Active         = 'active';
    case StillFaulty    = 'still_faulty';
    case Decommissioned = 'decommissioned';
    case Replaced       = 'replaced';
    case NoChange       = 'no_change';

    public function label(): string
    {
        return match ($this) {
            self::Active         => 'Active (resolved)',
            self::StillFaulty    => 'Still Faulty',
            self::Decommissioned => 'Decommissioned',
            self::Replaced       => 'Replaced',
            self::NoChange       => 'No change',
        };
    }

    /**
     * Map to the AssetStatus transition applied on PM validation (US-10.4, EPIC-11).
     * Null means "leave the asset as it is" — e.g. a spare on site that was not touched.
     */
    public function toAssetStatus(): ?AssetStatus
    {
        return match ($this) {
            self::Active         => AssetStatus::Active,
            self::StillFaulty    => AssetStatus::Faulty,
            self::Decommissioned => AssetStatus::Decommissioned,
            // The physical unit was swapped out — this asset record is retired;
            // the replacement is registered as a separate asset.
            self::Replaced => AssetStatus::Decommissioned,
            self::NoChange => null,
        };
    }
}
