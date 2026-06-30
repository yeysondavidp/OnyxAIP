<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\JobStatus;
use App\Enums\PhotoType;
use App\Enums\TechnicianJobStatus;
use App\Jobs\WriteAuditLog;
use App\Models\Asset;
use App\Models\JobAssetOutcome;
use App\Models\JobCheckpoint;
use App\Models\JobPhoto;
use App\Models\ServiceJob;
use App\Models\TechnicianProfile;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Centralised logic for the 5-screen technician mobile workflow (EPIC-10).
 *
 * Checkpoints (Start, Complete) and photo uploads are the only moments the
 * server is contacted — all client-side Alpine state lives between them per ADR-001.
 */
class JobFlowService
{
    public function __construct(
        private readonly AssetTransitionService $assetTransitionService,
        private readonly JobTransitionService $jobTransitionService,
    ) {}

    // ── Start checkpoint (Screen 2) ────────────────────────────────────────────

    /**
     * Authoritative Start checkpoint.
     *
     * - Validates early-start window (server-side re-check of US-10.1 client gate).
     * - Records StartTimestampUTC + GPS server-side.
     * - Advances technician pivot: accepted|invited → started.
     * - Rolls job to InProgress when the first technician starts (via US-08.3 guard).
     *
     * @param  array{lat?: float|null, lng?: float|null, gps_status: string}  $gps
     *
     * @throws \InvalidArgumentException when the early-start window is not yet open
     */
    public function startJob(
        ServiceJob $job,
        TechnicianProfile $profile,
        array $gps,
    ): void {
        $this->enforceEarlyStartWindow($job);

        DB::transaction(function () use ($job, $profile, $gps): void {
            // Upsert checkpoint with start data
            JobCheckpoint::updateOrCreate(
                ['job_id' => $job->id, 'technician_profile_id' => $profile->id],
                [
                    'start_timestamp_utc' => now(),
                    'start_lat'           => $gps['lat'] ?? null,
                    'start_lng'           => $gps['lng'] ?? null,
                    'start_gps_status'    => $gps['gps_status'],
                ]
            );

            // Advance this technician's pivot status
            DB::table('job_technicians')
                ->where('job_id', $job->id)
                ->where('technician_profile_id', $profile->id)
                ->whereIn('technician_status', [
                    TechnicianJobStatus::Invited->value,
                    TechnicianJobStatus::Accepted->value,
                ])
                ->update(['technician_status' => TechnicianJobStatus::Started->value]);
        });

        // Roll job to InProgress when this is the first start.
        // The job may be Invited (if not all technicians have formally accepted at job level)
        // or Accepted — step through both transitions as needed.
        $job->refresh();
        if ($job->job_status === JobStatus::Invited) {
            try {
                $this->jobTransitionService->transitionTo($job, JobStatus::Accepted);
            } catch (\InvalidArgumentException) {
                // Already past Invited
            }
        }

        if ($job->job_status === JobStatus::Accepted) {
            try {
                $this->jobTransitionService->transitionTo($job, JobStatus::InProgress);
            } catch (\InvalidArgumentException) {
                // Already InProgress from another technician's start — no-op
            }
        }

        WriteAuditLog::dispatch(
            userId: null,
            userRole: null,
            action: 'job.started',
            auditableType: 'App\\Models\\ServiceJob',
            auditableId: $job->id,
            before: null,
            after: [
                'technician_profile_id' => $profile->id,
                'gps_status'            => $gps['gps_status'],
            ],
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
        );
    }

    /**
     * Cancel Screen 2 — revert this technician from started → accepted.
     */
    public function cancelStart(ServiceJob $job, TechnicianProfile $profile): void
    {
        DB::table('job_technicians')
            ->where('job_id', $job->id)
            ->where('technician_profile_id', $profile->id)
            ->where('technician_status', TechnicianJobStatus::Started->value)
            ->update(['technician_status' => TechnicianJobStatus::Accepted->value]);

        // Discard any before-photos already uploaded in this aborted session
        $photos = JobPhoto::where('job_id', $job->id)
            ->where('technician_profile_id', $profile->id)
            ->where('type', PhotoType::Before->value)
            ->get();

        foreach ($photos as $photo) {
            Storage::disk('local')->delete($photo->stored_path);
            $photo->delete();
        }
    }

    // ── Photo upload (US-10.6) ─────────────────────────────────────────────────

    /**
     * Store a single photo — idempotent on client_upload_id per (job, profile).
     *
     * Returns the server-side JobPhoto id on first insert, or the existing id on retry.
     *
     * @throws \InvalidArgumentException on disallowed MIME type
     */
    public function storePhoto(
        ServiceJob $job,
        TechnicianProfile $profile,
        PhotoType $type,
        UploadedFile $file,
        string $clientUploadId,
    ): int {
        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/heic',
            'image/heif',
        ];

        $detectedMime = $file->getMimeType() ?? '';
        if (! in_array($detectedMime, $allowedMimes, strict: true)) {
            throw new \InvalidArgumentException("File type [{$detectedMime}] is not permitted for photos.");
        }

        // Idempotency — return existing record if already uploaded
        $existing = JobPhoto::where('job_id', $job->id)
            ->where('technician_profile_id', $profile->id)
            ->where('client_upload_id', $clientUploadId)
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $subDir = "job-photos/{$job->id}/{$profile->id}/{$type->value}";
        $path   = $file->store($subDir, 'local');

        $photo = JobPhoto::create([
            'job_id'                => $job->id,
            'technician_profile_id' => $profile->id,
            'type'                  => $type->value,
            'stored_path'           => (string) $path,
            'mime_type'             => $detectedMime,
            'file_size'             => $file->getSize() ?: null,
            'client_upload_id'      => $clientUploadId,
        ]);

        WriteAuditLog::dispatch(
            userId: null,
            userRole: null,
            action: 'job.photo_uploaded',
            auditableType: 'App\\Models\\ServiceJob',
            auditableId: $job->id,
            before: null,
            after: ['type' => $type->value, 'technician_profile_id' => $profile->id],
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
        );

        return $photo->id;
    }

    // ── In-flow asset status update (Screen 3, US-10.3) ───────────────────────

    /**
     * Update an asset's status during the visit via EPIC-06 transition service.
     *
     * @throws \InvalidArgumentException on disallowed transition
     */
    public function updateAssetStatus(
        ServiceJob $job,
        Asset $asset,
        AssetStatus $newStatus,
    ): void {
        // Verify this asset belongs to this job
        if (! $job->assets()->where('assets.id', $asset->id)->exists()) {
            throw new \InvalidArgumentException('Asset does not belong to this job.');
        }

        $this->assetTransitionService->transitionTo(
            $asset,
            $newStatus,
            systemLabel: 'system:technician_in_flow'
        );
    }

    // ── Complete checkpoint (Screen 4) ────────────────────────────────────────

    /**
     * Authoritative Complete checkpoint.
     *
     * - Records EndTimestampUTC + end GPS server-side.
     * - Persists per-asset outcomes.
     * - Advances technician pivot: started → completed.
     * - Rolls job to Completed when all technicians have submitted (US-08.4 rule).
     * All writes in one transaction to prevent partial completion.
     *
     * @param  array{lat?: float|null, lng?: float|null, gps_status: string}  $gps
     * @param  list<array{asset_id: int, post_service_status: string, notes?: string}>  $outcomes
     */
    public function completeJob(
        ServiceJob $job,
        TechnicianProfile $profile,
        array $gps,
        array $outcomes,
        ?string $completionNotes,
    ): void {
        DB::transaction(function () use ($job, $profile, $gps, $outcomes, $completionNotes): void {
            // Update checkpoint with end data
            JobCheckpoint::updateOrCreate(
                ['job_id' => $job->id, 'technician_profile_id' => $profile->id],
                [
                    'end_timestamp_utc' => now(),
                    'end_lat'           => $gps['lat'] ?? null,
                    'end_lng'           => $gps['lng'] ?? null,
                    'end_gps_status'    => $gps['gps_status'],
                    'completion_notes'  => $completionNotes,
                ]
            );

            // Bulk-upsert asset outcomes
            foreach ($outcomes as $outcome) {
                JobAssetOutcome::updateOrCreate(
                    ['job_id' => $job->id, 'asset_id' => (int) $outcome['asset_id']],
                    [
                        'post_service_status' => $outcome['post_service_status'],
                        'technician_notes'    => $outcome['notes'] ?? null,
                    ]
                );
            }

            // Advance this technician's pivot status
            DB::table('job_technicians')
                ->where('job_id', $job->id)
                ->where('technician_profile_id', $profile->id)
                ->where('technician_status', TechnicianJobStatus::Started->value)
                ->update(['technician_status' => TechnicianJobStatus::Completed->value]);
        });

        // If all technicians are now completed, roll job to Completed
        $job->refresh();
        if ($job->allTechniciansCompleted()) {
            try {
                $this->jobTransitionService->transitionTo($job, JobStatus::Completed);
            } catch (\InvalidArgumentException) {
                // Already Completed — no-op
            }
        }

        WriteAuditLog::dispatch(
            userId: null,
            userRole: null,
            action: 'job.completed',
            auditableType: 'App\\Models\\ServiceJob',
            auditableId: $job->id,
            before: null,
            after: ['technician_profile_id' => $profile->id],
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
        );
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Enforce early-start window server-side (authoritative check for US-10.1 client gate).
     *
     * @throws \InvalidArgumentException when outside the allowed window
     */
    private function enforceEarlyStartWindow(ServiceJob $job): void
    {
        $window  = $job->early_start_window;
        $minutes = $window->minutes();

        if ($minutes === null || ! $job->scheduled_date || ! $job->scheduled_time) {
            return; // Anytime window or no schedule
        }

        $scheduledUtc = Carbon::parse(
            Carbon::parse((string) $job->scheduled_date)->format('Y-m-d').' '.$job->scheduled_time,
            'UTC'
        );

        $earliestStart = $scheduledUtc->copy()->subMinutes($minutes);

        if (now()->lt($earliestStart)) {
            $localTime = $scheduledUtc->setTimezone($job->job_timezone)->format('g:i A');
            throw new \InvalidArgumentException(
                "You can start this job from {$earliestStart->setTimezone($job->job_timezone)->format('g:i A')} ".
                "(scheduled at {$localTime})."
            );
        }
    }

    /**
     * Log a GPS failure for observability without blocking the flow (§14.6).
     */
    public function logGpsFailure(int $jobId, int $profileId, string $reason): void
    {
        Log::channel('daily')->warning('technician_gps_failure', [
            'job_id'                => $jobId,
            'technician_profile_id' => $profileId,
            'reason'                => $reason,
            'ip'                    => request()->ip(),
        ]);
    }
}
