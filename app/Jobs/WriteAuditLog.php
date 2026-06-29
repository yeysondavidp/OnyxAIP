<?php

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Writes a single audit log entry asynchronously.
 *
 * Dispatched by the Auditable trait on model events. Retried up to 5 times
 * on failure so no audit entry is silently lost (§14.5, ADR-002).
 */
class WriteAuditLog implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $backoff = 10;

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function __construct(
        public readonly ?int $userId,
        public readonly ?string $userRole,
        public readonly string $action,
        public readonly string $auditableType,
        public readonly int $auditableId,
        public readonly ?array $before,
        public readonly ?array $after,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
    ) {}

    public function handle(): void
    {
        AuditLog::create([
            'user_id'        => $this->userId,
            'user_role'      => $this->userRole,
            'action'         => $this->action,
            'auditable_type' => $this->auditableType,
            'auditable_id'   => $this->auditableId,
            'before'         => $this->before,
            'after'          => $this->after,
            'ip_address'     => $this->ipAddress,
            'user_agent'     => $this->userAgent,
        ]);
    }
}
