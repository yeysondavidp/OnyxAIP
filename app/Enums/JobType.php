<?php

namespace App\Enums;

enum JobType: string
{
    case RoutineMaintenance = 'routine_maintenance';
    case FaultRepair        = 'fault_repair';
    case NewInstallation    = 'new_installation';
    case Deinstall          = 'deinstall';
    case Survey             = 'survey';
    case Other              = 'other';

    public function label(): string
    {
        return match ($this) {
            self::RoutineMaintenance => 'Routine Maintenance',
            self::FaultRepair        => 'Fault Repair',
            self::NewInstallation    => 'New Installation',
            self::Deinstall          => 'Deinstall',
            self::Survey             => 'Survey',
            self::Other              => 'Other',
        };
    }
}
