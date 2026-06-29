<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Enforces client_id tenancy on every query for scoped models.
 *
 * The authenticated user drives what is visible:
 * - null returned from permittedClientIds() → PM with unrestricted access
 * - array returned → filter to those client IDs only (client_user in v2)
 *
 * Unauthenticated contexts (guest/signed-URL technician flow) must be
 * handled by route middleware before reaching tenant-scoped resources.
 */
class ClientScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $permitted = $user->permittedClientIds();

        if ($permitted === null) {
            // Unrestricted — PM sees all clients
            return;
        }

        $builder->whereIn($model->qualifyColumn('client_id'), $permitted);
    }
}
