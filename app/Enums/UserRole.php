<?php

namespace App\Enums;

/**
 * The three roles in v1. client_user is defined here but has no active portal
 * in v1 (§16 Q1) — it must never route to a PM or technician surface.
 */
enum UserRole: string
{
    case Pm         = 'pm';
    case Technician = 'technician';
    case ClientUser = 'client_user'; // dormant in v1, activated in v2

    public function label(): string
    {
        return match ($this) {
            self::Pm         => 'Project Manager',
            self::Technician => 'Technician',
            self::ClientUser => 'Client User',
        };
    }

    public function isPm(): bool
    {
        return $this === self::Pm;
    }

    public function isTechnician(): bool
    {
        return $this === self::Technician;
    }

    public function isClientUser(): bool
    {
        return $this === self::ClientUser;
    }

    /** True only for roles that may enter the authenticated PM portal in v1. */
    public function canAccessPmPortal(): bool
    {
        return $this === self::Pm;
    }

    /** True for roles that access jobs via the technician signed-URL flow. */
    public function canAccessTechnicianFlow(): bool
    {
        return $this === self::Technician;
    }
}
