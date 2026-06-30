<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Active           = 'active';
    case Faulty           = 'faulty';
    case Offline          = 'offline';
    case UnderMaintenance = 'under_maintenance';
    case Decommissioned   = 'decommissioned';

    public function label(): string
    {
        return match ($this) {
            self::Active           => 'Active',
            self::Faulty           => 'Faulty',
            self::Offline          => 'Offline',
            self::UnderMaintenance => 'Under Maintenance',
            self::Decommissioned   => 'Decommissioned',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active           => 'positive',
            self::Faulty           => 'critical',
            self::Offline          => 'warning',
            self::UnderMaintenance => 'info',
            self::Decommissioned   => 'neutral',
        };
    }
}
