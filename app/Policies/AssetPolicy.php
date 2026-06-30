<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\User;

/**
 * Authorises Asset actions by role AND client_id scope (Engineering Bar #1).
 *
 * ClientScope handles the read side (SELECTs auto-filtered); this policy
 * enforces the write side and model-level view checks. Both layers are required.
 */
class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Pm || $user->role === UserRole::ClientUser;
    }

    public function view(User $user, Asset $asset): bool
    {
        return $this->withinScope($user, $asset);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Pm;
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->role === UserRole::Pm && $this->withinScope($user, $asset);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->role === UserRole::Pm && $this->withinScope($user, $asset);
    }

    private function withinScope(User $user, Asset $asset): bool
    {
        $permitted = $user->permittedClientIds();

        if ($permitted === null) {
            return true;
        }

        return in_array($asset->client_id, $permitted, strict: true);
    }
}
