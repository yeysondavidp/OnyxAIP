<?php

namespace App\Enums;

enum LightType: string
{
    case Led         = 'led';
    case Fluorescent = 'fluorescent';
    case Other       = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Led         => 'LED',
            self::Fluorescent => 'Fluorescent',
            self::Other       => 'Other',
        };
    }
}
