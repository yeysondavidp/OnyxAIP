<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\TechnicianProfile;
use App\Models\User;

class TechnicianProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Pm;
    }

    public function view(User $user, TechnicianProfile $profile): bool
    {
        return $user->role === UserRole::Pm;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Pm;
    }

    public function update(User $user, TechnicianProfile $profile): bool
    {
        return $user->role === UserRole::Pm;
    }

    public function delete(User $user, TechnicianProfile $profile): bool
    {
        return $user->role === UserRole::Pm;
    }
}
