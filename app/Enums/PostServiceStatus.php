<?php

namespace App\Enums;

enum PostServiceStatus: string
{
    case Active         = 'active';
    case StillFaulty    = 'still_faulty';
    case Decommissioned = 'decommissioned';
    case Replaced       = 'replaced';

    public function label(): string
    {
        return match ($this) {
            self::Active         => 'Active (resolved)',
            self::StillFaulty    => 'Still Faulty',
            self::Decommissioned => 'Decommissioned',
            self::Replaced       => 'Replaced',
        };
    }

    /** Map to the AssetStatus transition applied on PM validation (US-10.4, EPIC-11). */
    public function toAssetStatus(): AssetStatus
    {
        return match ($this) {
            self::Active         => AssetStatus::Active,
            self::StillFaulty    => AssetStatus::Faulty,
            self::Decommissioned => AssetStatus::Decommissioned,
            // The physical unit was swapped out — this asset record is retired;
            // the replacement is registered as a separate asset.
            self::Replaced => AssetStatus::Decommissioned,
        };
    }
}
