<?php

namespace App\Enums;

enum MonitoringCoverage: string
{
    case TwentyFourSeven   = '24_7';
    case BusinessHoursOnly = 'business_hours_only';

    public function label(): string
    {
        return match ($this) {
            self::TwentyFourSeven   => '24/7 automated',
            self::BusinessHoursOnly => 'Business hours only',
        };
    }
}
