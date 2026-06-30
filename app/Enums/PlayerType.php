<?php

namespace App\Enums;

enum PlayerType: string
{
    case StandaloneHardware = 'standalone_hardware';
    case SocApp             = 'soc_app';

    public function label(): string
    {
        return match ($this) {
            self::StandaloneHardware => 'Standalone Hardware',
            self::SocApp             => 'SoC App',
        };
    }
}
