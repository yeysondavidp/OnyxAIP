<?php

namespace App\Traits;

use App\Jobs\WriteAuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Attach to any model to get async, append-only audit logging (§14.5).
 *
 * Hooks into created / updated / deleted model events and dispatches a
 * WriteAuditLog job for each. The job is retried on failure (5 tries)
 * so no entry is silently lost.
 *
 * Sensitive fields (passwords, tokens, secrets) are stripped from before/after
 * diffs before the payload is built — never stored in the audit log.
 *
 * Usage:
 *   class Asset extends BaseModel {
 *       use Auditable;
 *   }
 *
 * Significant actions mapped to SRA §14.5:
 *   created        — model first persisted
 *   updated        — any attribute change (use status_changed for status fields)
 *   deleted        — soft or hard delete
 *   status_changed — emitted by models that call auditStatusChange() explicitly
 */
trait Auditable
{
    /** Fields that must never appear in audit diffs. */
    protected static array $sensitiveFields = [
        'password', 'remember_token', 'api_token', 'token',
        'secret', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            static::dispatchAuditLog(
                action: 'created',
                model: $model,
                before: null,
                after: static::strip($model->getAttributes()),
            );
        });

        static::updated(function (Model $model) {
            static::dispatchAuditLog(
                action: 'updated',
                model: $model,
                before: static::strip($model->getOriginal()),
                after: static::strip($model->getChanges()),
            );
        });

        static::deleted(function (Model $model) {
            static::dispatchAuditLog(
                action: 'deleted',
                model: $model,
                before: static::strip($model->getAttributes()),
                after: null,
            );
        });
    }

    /**
     * Emit a status_changed audit entry explicitly.
     *
     * Call this from model methods that transition status (e.g. Asset::markFaulty())
     * in addition to updating the attribute, so the audit viewer can distinguish
     * status transitions from general attribute edits.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function auditStatusChange(array $before, array $after): void
    {
        static::dispatchAuditLog(
            action: 'status_changed',
            model: $this,
            before: $before,
            after: $after,
        );
    }

    /** @param array<string, mixed>|null $before  @param array<string, mixed>|null $after */
    protected static function dispatchAuditLog(
        string $action,
        Model $model,
        ?array $before,
        ?array $after,
    ): void {
        $user   = auth()->user();
        $userId = $user?->getKey();
        // role column added in US-01.2 — guard against it not existing yet
        $userRole = ($user && isset($user->role)) ? (string) $user->role : null;

        WriteAuditLog::dispatch(
            userId: is_int($userId) ? $userId : ($userId !== null ? (int) $userId : null),
            userRole: $userRole,
            action: $action,
            auditableType: $model->getMorphClass(),
            auditableId: (int) $model->getKey(),
            before: $before,
            after: $after,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
        );
    }

    /**
     * Strip sensitive fields from an attribute array before it is stored.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected static function strip(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(static::$sensitiveFields));
    }
}
