<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;

/**
 * Authorises Store actions by role AND client_id scope (Engineering Bar #1).
 *
 * The ClientScope global scope handles the read side (SELECTs are already
 * filtered), but policies enforce the write side and model-level view checks.
 * Both layers must agree — neither alone is sufficient.
 */
class StorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Pm || $user->role === UserRole::ClientUser;
    }

    public function view(User $user, Store $store): bool
    {
        return $this->withinScope($user, $store);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Pm;
    }

    public function update(User $user, Store $store): bool
    {
        return $user->role === UserRole::Pm && $this->withinScope($user, $store);
    }

    public function delete(User $user, Store $store): bool
    {
        return $user->role === UserRole::Pm && $this->withinScope($user, $store);
    }

    /**
     * True when the user is permitted to act on this store's tenant.
     *
     * PM is always in scope (permittedClientIds returns null).
     * client_user is only in scope if the store belongs to their client.
     */
    private function withinScope(User $user, Store $store): bool
    {
        $permitted = $user->permittedClientIds();

        if ($permitted === null) {
            return true; // PM — unrestricted
        }

        return in_array($store->client_id, $permitted, strict: true);
    }
}
