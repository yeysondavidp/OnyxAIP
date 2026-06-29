<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only audit log entry — never updated, never deleted.
 *
 * Records are written exclusively via the WriteAuditLog job.
 * All mutations through Eloquent (update, delete) are blocked at the
 * application layer. The DB has no updated_at column (TIMESTAMPS = null).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $user_role
 * @property string $action
 * @property string $auditable_type
 * @property int $auditable_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 */
class AuditLog extends Model
{
    // Append-only: no updated_at column exists.
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'user_role',
        'action',
        'auditable_type',
        'auditable_id',
        'before',
        'after',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after'  => 'array',
        ];
    }

    // ── Append-only enforcement ───────────────────────────────────────────

    /** @param array<string, mixed> $attributes */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('Audit log entries are append-only and cannot be updated.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('Audit log entries are append-only and cannot be deleted.');
    }

    public function forceDelete(): ?bool
    {
        throw new LogicException('Audit log entries are append-only and cannot be deleted.');
    }

    // ── Relations ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
