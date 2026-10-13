<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Agent accounts and their tokens (US-20.1) — PM only, never another agent,
 * so a leaked agent token can never mint or revoke tokens.
 */
class UserPolicy
{
    public function manageAgents(User $user): bool
    {
        return $user->role === UserRole::Pm;
    }
}
