<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;

/**
 * Reference authorisation pattern for tenant-root models (§US-01.3).
 *
 * Every subsequent model policy (Store, Asset, Job…) MUST follow this
 * four-layer structure:
 *   UI (hide/disable) + Form Request (authorize + validate)
 *   + Policy (role AND client_id scope) + DB (NN + FK + index).
 *
 * Client is the tenant root, so it is NOT scoped by client_id itself —
 * only by role. A client_user may only view their own client record.
 */
class ClientPolicy
{
    /** PM sees the list; client_user sees only their own — index filtering
     *  happens via ClientScope, so viewAny just gates portal entry. */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Pm || $user->role === UserRole::ClientUser;
    }

    public function view(User $user, Client $client): bool
    {
        if ($user->role === UserRole::Pm) {
            return true;
        }

        // client_user may only view their own client record.
        if ($user->role === UserRole::ClientUser) {
            return $user->client_id === $client->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Pm;
    }

    public function update(User $user, Client $client): bool
    {
        return $user->role === UserRole::Pm;
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->role === UserRole::Pm;
    }
}
