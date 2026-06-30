<?php

namespace App\Enums;

enum EarlyStartWindow: string
{
    case Anytime   = 'anytime';
    case ThirtyMin = '30_min';
    case OneHour   = '1_hr';
    case TwoHour   = '2_hr';
    case FourHour  = '4_hr';

    public function label(): string
    {
        return match ($this) {
            self::Anytime   => 'Anytime',
            self::ThirtyMin => '30 minutes',
            self::OneHour   => '1 hour',
            self::TwoHour   => '2 hours',
            self::FourHour  => '4 hours',
        };
    }

    /** Returns the window in minutes, or null for "anytime". */
    public function minutes(): ?int
    {
        return match ($this) {
            self::Anytime   => null,
            self::ThirtyMin => 30,
            self::OneHour   => 60,
            self::TwoHour   => 120,
            self::FourHour  => 240,
        };
    }
}
