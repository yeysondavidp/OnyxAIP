<?php

namespace App\Enums;

enum TotemSuppliedBy: string
{
    case Client = 'client';
    case Onyx   = 'onyx';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Client',
            self::Onyx   => 'ONYX',
        };
    }
}
