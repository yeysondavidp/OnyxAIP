<?php

namespace App\Traits;

use App\Scopes\ClientScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Apply to every model that belongs to a single client tenant.
 *
 * What this trait does:
 *   1. Registers ClientScope as a global scope → every SELECT is filtered.
 *   2. On creating: sets client_id from the authenticated user's context
 *      so it is never trusted from request input (Engineering Bar #1).
 *
 * ── Migration recipe ───────────────────────────────────────────────────
 *   Every migration for a tenant-scoped table MUST include:
 *
 *     $table->foreignId('client_id')
 *           ->constrained()          // FK → clients.id, ON DELETE RESTRICT
 *           ->index();               // indexed for query performance (§14.4)
 *
 *   NOT NULL is enforced by foreignId() (unsigned bigint NOT NULL).
 *   The index keeps JOIN and WHERE client_id = ? queries fast at scale.
 * ────────────────────────────────────────────────────────────────────────
 *
 * Usage:
 *   class Store extends BaseModel {
 *       use ClientScoped;
 *       protected $fillable = ['client_id', 'name', …];
 *   }
 */
trait ClientScoped
{
    protected static function bootClientScoped(): void
    {
        static::addGlobalScope(new ClientScope);

        static::creating(function (Model $model) {
            if ($model->getAttribute('client_id') !== null) {
                return; // explicitly set (e.g. seeder / admin action)
            }

            $user = auth()->user();

            if (! $user) {
                return;
            }

            $permitted = $user->permittedClientIds();

            // For a restricted user (future client_user), auto-set to their client.
            if ($permitted !== null && count($permitted) === 1) {
                $model->setAttribute('client_id', $permitted[0]);
            }
        });
    }

    /**
     * Query builder shortcut — bypass the scope for cross-tenant admin work.
     *
     * Use sparingly (e.g. migration helpers, super-admin tools).
     * Never call this from a controller handling a client_user request.
     *
     * @return Builder<static>
     */
    public static function allClients(): Builder
    {
        return static::query()->withoutGlobalScope(ClientScope::class);
    }
}
