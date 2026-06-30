<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\DisplayGroup;
use App\Models\Store;
use App\Models\User;

/**
 * Authorises DisplayGroup actions by role AND client_id scope (US-05.1 / Engineering Bar #1).
 *
 * client_id is always derived from the store relation — never from request input.
 */
class DisplayGroupPolicy
{
    public function viewAny(User $user, Store $store): bool
    {
        return $user->role === UserRole::Pm && $this->storeInScope($user, $store);
    }

    public function create(User $user, Store $store): bool
    {
        return $user->role === UserRole::Pm && $this->storeInScope($user, $store);
    }

    public function update(User $user, DisplayGroup $group): bool
    {
        return $user->role === UserRole::Pm && $this->groupInScope($user, $group);
    }

    public function delete(User $user, DisplayGroup $group): bool
    {
        return $user->role === UserRole::Pm && $this->groupInScope($user, $group);
    }

    private function storeInScope(User $user, Store $store): bool
    {
        $permitted = $user->permittedClientIds();

        if ($permitted === null) {
            return true;
        }

        return in_array($store->client_id, $permitted, strict: true);
    }

    private function groupInScope(User $user, DisplayGroup $group): bool
    {
        $group->loadMissing('store');

        $store = $group->store;

        if (! $store instanceof Store) {
            return false;
        }

        return $this->storeInScope($user, $store);
    }
}
