<?php

namespace App\Enums;

enum TechnicianJobStatus: string
{
    case Invited   = 'invited';
    case Accepted  = 'accepted';
    case Declined  = 'declined';
    case Started   = 'started';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Invited   => 'Invited',
            self::Accepted  => 'Accepted',
            self::Declined  => 'Declined',
            self::Started   => 'Started',
            self::Completed => 'Completed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Invited   => 'info',
            self::Accepted  => 'positive',
            self::Declined  => 'critical',
            self::Started   => 'warning',
            self::Completed => 'positive',
        };
    }
}
