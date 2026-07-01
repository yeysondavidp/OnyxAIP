<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SlaProfile;
use App\Models\User;

/**
 * SLA profiles are internal ONYX configuration (§10.1) — PM-only for every
 * action, unlike Client which also grants a read-only client_user view.
 */
class SlaProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Pm;
    }

    public function view(User $user, SlaProfile $slaProfile): bool
    {
        return $user->role === UserRole::Pm;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Pm;
    }

    public function update(User $user, SlaProfile $slaProfile): bool
    {
        return $user->role === UserRole::Pm;
    }

    public function delete(User $user, SlaProfile $slaProfile): bool
    {
        return $user->role === UserRole::Pm;
    }
}
