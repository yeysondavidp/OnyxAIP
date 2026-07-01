<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Platform settings have no per-row owner to check against — authorised purely by
 * role, same class-based pattern as ClientPolicy::create() (US-16.1).
 */
class PlatformSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Pm;
    }

    public function update(User $user): bool
    {
        return $user->role === UserRole::Pm;
    }
}
