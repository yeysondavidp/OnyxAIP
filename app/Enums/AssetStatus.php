<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Active           = 'active';
    case Faulty           = 'faulty';
    case Offline          = 'offline';
    case UnderMaintenance = 'under_maintenance';
    case Decommissioned   = 'decommissioned';
    // Found on site by a technician, not yet checked by a PM (D7, EPIC-21). Hidden from
    // every report and list by VerifiedAssetScope; never selectable by hand.
    case PendingVerification = 'pending_verification';

    public function label(): string
    {
        return match ($this) {
            self::Active              => 'Active',
            self::Faulty              => 'Faulty',
            self::Offline             => 'Offline',
            self::UnderMaintenance    => 'Under Maintenance',
            self::Decommissioned      => 'Decommissioned',
            self::PendingVerification => 'Pending verification',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active              => 'positive',
            self::Faulty              => 'critical',
            self::Offline             => 'warning',
            self::UnderMaintenance    => 'info',
            self::Decommissioned      => 'neutral',
            self::PendingVerification => 'accent',
        };
    }

    /**
     * Statuses a person may pick in a form, filter or tool. Pending verification is only ever
     * set by FoundDeviceService and cleared by a PM verifying the device.
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return [self::Active, self::Faulty, self::Offline, self::UnderMaintenance, self::Decommissioned];
    }
}
