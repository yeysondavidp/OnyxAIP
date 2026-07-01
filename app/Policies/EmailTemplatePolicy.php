<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Templates are keyed by slot (an enum), not always-persisted rows — "using
 * default" means no row exists yet — so every action here is authorised by
 * role alone, same class-based pattern as PlatformSettingPolicy (US-16.2).
 */
class EmailTemplatePolicy
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
