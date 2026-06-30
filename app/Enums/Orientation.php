<?php

namespace App\Enums;

enum Orientation: string
{
    case Landscape = 'landscape';
    case Portrait  = 'portrait';

    public function label(): string
    {
        return match ($this) {
            self::Landscape => 'Landscape',
            self::Portrait  => 'Portrait',
        };
    }
}
