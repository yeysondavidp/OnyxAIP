<?php

namespace App\Services;

use App\Enums\JobStatus;
use App\Jobs\WriteAuditLog;
use App\Models\ServiceJob;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for all service-job status transitions (US-08.3, SRA §5.3).
 *
 * Every caller — PM UI, technician flow, force-complete — MUST route through
 * transitionTo(). Direct writes to job_status bypass the guard and are forbidden.
 *
 * Permitted transitions (SRA §5.3):
 *   Draft               → Invited, Cancelled
 *   Invited             → Accepted, Cancelled
 *   Accepted            → InProgress, Cancelled
 *   InProgress          → Completed, Cancelled
 *   Completed           → Validated, RequiresRemediation, Cancelled
 *   Validated           → (terminal)
 *   RequiresRemediation → Cancelled
 *   Cancelled           → (terminal via soft-delete)
 *
 * PM-only transitions: Invited, Validated, RequiresRemediation, Cancelled.
 * Technician-triggered transitions: Accepted, InProgress, Completed (via EPIC-10).
 */
class JobTransitionService
{
    /** @var array<string, list<string>> permitted targets keyed by current status value */
    private const PERMITTED = [
        'draft'                => ['invited', 'cancelled'],
        'invited'              => ['accepted', 'cancelled'],
        'accepted'             => ['in_progress', 'cancelled'],
        'in_progress'          => ['completed', 'cancelled'],
        'completed'            => ['validated', 'requires_remediation', 'cancelled'],
        'validated'            => [],
        'requires_remediation' => ['cancelled'],
        'cancelled'            => [],
    ];

    /**
     * Attempt to transition a job to a new status.
     *
     * Passes $reason through when force-completing or cancelling.
     *
     * @throws \InvalidArgumentException when the transition is not permitted
     */
    public function transitionTo(
        ServiceJob $job,
        JobStatus $newStatus,
        ?User $actor = null,
        ?string $reason = null,
    ): void {
        $current = $job->job_status;

        if (! $this->isPermitted($current, $newStatus)) {
            throw new \InvalidArgumentException(
                "Job transition from [{$current->value}] to [{$newStatus->value}] is not permitted."
            );
        }

        if ($current === $newStatus) {
            return; // no-op
        }

        DB::transaction(function () use ($job, $newStatus, $reason): void {
            $update = ['job_status' => $newStatus->value, 'updated_at' => now()];

            if ($reason !== null) {
                $update['force_complete_reason'] = $reason;
            }

            // Soft-delete on cancel (US-08.3)
            if ($newStatus === JobStatus::Cancelled) {
                DB::table('service_jobs')
                    ->where('id', $job->id)
                    ->update(array_merge($update, ['deleted_at' => now()]));
            } else {
                DB::table('service_jobs')
                    ->where('id', $job->id)
                    ->update($update);
            }

            $job->job_status = $newStatus;
        });

        WriteAuditLog::dispatch(
            userId: $actor?->id,
            userRole: $actor?->role?->value,
            action: 'status_changed',
            auditableType: 'App\\Models\\ServiceJob',
            auditableId: $job->id,
            before: ['job_status' => $current->value],
            after: array_filter(['job_status' => $newStatus->value, 'reason' => $reason]),
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
        );

        // PM notification on job completion / remediation flag (US-13.1). Resolved via the
        // container rather than constructor injection — this service is instantiated
        // directly (`new JobTransitionService`) at several existing call sites.
        if (in_array($newStatus, [JobStatus::Completed, JobStatus::RequiresRemediation], strict: true)) {
            app(NotificationDispatcher::class)->jobStatusChanged($job);
        }
    }

    public function isPermitted(JobStatus $from, JobStatus $to): bool
    {
        return in_array($to->value, self::PERMITTED[$from->value], strict: true);
    }

    /** @return list<JobStatus> */
    public function permittedTransitionsFrom(JobStatus $status): array
    {
        return array_map(
            fn (string $v) => JobStatus::from($v),
            self::PERMITTED[$status->value]
        );
    }
}
