<?php

namespace App\Enums;

enum AustralianState: string
{
    case Nsw = 'NSW';
    case Vic = 'VIC';
    case Qld = 'QLD';
    case Wa  = 'WA';
    case Sa  = 'SA';
    case Tas = 'TAS';
    case Act = 'ACT';
    case Nt  = 'NT';

    public function label(): string
    {
        return match ($this) {
            self::Nsw => 'New South Wales',
            self::Vic => 'Victoria',
            self::Qld => 'Queensland',
            self::Wa  => 'Western Australia',
            self::Sa  => 'South Australia',
            self::Tas => 'Tasmania',
            self::Act => 'Australian Capital Territory',
            self::Nt  => 'Northern Territory',
        };
    }
}
