<?php

namespace App\Enums;

enum SlaStatus: string
{
    case NotTracked = 'not_tracked';
    case OnTrack    = 'on_track';
    case AtRisk     = 'at_risk';
    case Breached   = 'breached';

    public function label(): string
    {
        return match ($this) {
            self::NotTracked => 'Not tracked',
            self::OnTrack    => 'On track',
            self::AtRisk     => 'At risk',
            self::Breached   => 'Breached',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::NotTracked => 'neutral',
            self::OnTrack    => 'positive',
            self::AtRisk     => 'caution',
            self::Breached   => 'critical',
        };
    }
}
