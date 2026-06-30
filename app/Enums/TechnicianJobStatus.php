<?php

namespace App\Enums;

enum TechnicianJobStatus: string
{
    case Invited   = 'invited';
    case Accepted  = 'accepted';
    case Started   = 'started';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Invited   => 'Invited',
            self::Accepted  => 'Accepted',
            self::Started   => 'Started',
            self::Completed => 'Completed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Invited   => 'info',
            self::Accepted  => 'info',
            self::Started   => 'warning',
            self::Completed => 'positive',
        };
    }
}
