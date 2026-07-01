<?php

namespace App\Services;

use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Enums\PhotoType;
use App\Enums\PostServiceStatus;
use App\Jobs\WriteAuditLog;
use App\Models\Asset;
use App\Models\Client;
use App\Models\JobAssetOutcome;
use App\Models\JobCheckpoint;
use App\Models\JobPhoto;
use App\Models\ServiceJob;
use App\Models\Store;
use App\Models\User;
use App\Services\Sla\SlaClockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Closes the loop between a completed technician visit and the asset registry
 * (US-11.1/11.2, SRA §5.2, §5.3, §7).
 *
 * Single entry point for:
 *   - validate():        Completed → Validated, asset auto-transitions, append-only
 *                         service_history write — all in one transaction.
 *   - flagRemediation():  Completed → RequiresRemediation + a remediation sub-job.
 *
 * Never bypass this service to write asset status or service_history directly —
 * it is the "guard in one place, not the controller" contract for the whole loop.
 */
class JobValidationService
{
    public function __construct(
        private readonly AssetTransitionService $assetTransitionService,
        private readonly JobTransitionService $jobTransitionService,
        private readonly SlaClockService $slaClockService,
    ) {}

    /**
     * Validate a Completed job: resolve each affected asset's outcome, transition it,
     * write an append-only service_history row, and move the job to Validated.
     *
     * @param  array<int, string>  $assetDecisions  asset_id => PostServiceStatus value (PM override; optional)
     *
     * @throws \InvalidArgumentException when the job is not Completed, an asset is out of
     *                                   scope, or a resolved transition is not permitted
     */
    public function validate(ServiceJob $job, User $actor, array $assetDecisions = []): void
    {
        $job->loadMissing(['assets', 'technicians']);

        $this->assertSameClientScope($job);

        $outcomesByAsset = JobAssetOutcome::where('job_id', $job->id)->get()->keyBy('asset_id');

        // Resolve every asset's target status up front so an illegal transition
        // is caught before any write happens (defence in depth, not a partial commit).
        $resolved = [];
        /** @var Asset $asset */
        foreach ($job->assets as $asset) {
            $decision = $assetDecisions[$asset->id] ?? null;
            /** @var JobAssetOutcome|null $outcome */
            $outcome = $outcomesByAsset->get($asset->id);

            if ($decision !== null) {
                $postStatus = PostServiceStatus::from($decision);
            } elseif ($outcome !== null) {
                $postStatus = $outcome->post_service_status;
            } else {
                $postStatus = PostServiceStatus::Active;
            }

            $targetStatus = $postStatus->toAssetStatus();

            if ($targetStatus !== $asset->asset_status
                && ! $this->assetTransitionService->isPermitted($asset->asset_status, $targetStatus)) {
                throw new \InvalidArgumentException(
                    "Cannot resolve asset [{$asset->asset_code}] to [{$targetStatus->value}] — ".
                    "transition from [{$asset->asset_status->value}] is not permitted."
                );
            }

            $resolved[$asset->id] = [
                'asset'         => $asset,
                'status_before' => $asset->pivot->status_before ?? $asset->asset_status->value,
                'status_after'  => $targetStatus,
                'notes'         => $outcome?->technician_notes,
            ];
        }

        $technicianIds    = $job->technicians->pluck('id')->values()->all();
        $serviceDate      = $this->resolveServiceDate($job);
        $beforePhotoPaths = $this->photoPaths($job, PhotoType::Before);
        $afterPhotoPaths  = $this->photoPaths($job, PhotoType::After);

        DB::transaction(function () use (
            $job, $actor, $resolved, $technicianIds, $serviceDate, $beforePhotoPaths, $afterPhotoPaths
        ): void {
            $historyRows = [];

            foreach ($resolved as $r) {
                /** @var Asset $asset */
                $asset = $r['asset'];

                if ($r['status_after'] !== $asset->asset_status) {
                    $this->assetTransitionService->transitionTo(
                        $asset,
                        $r['status_after'],
                        actor: $actor,
                        reason: 'Job validation ('.$job->job_reference.')'
                    );
                }

                $historyRows[] = [
                    'asset_id'               => $asset->id,
                    'service_job_id'         => $job->id,
                    'service_date'           => $serviceDate->format('Y-m-d'),
                    'technician_profile_ids' => json_encode($technicianIds),
                    'job_type'               => $job->job_type->value,
                    'status_before'          => $r['status_before'],
                    'status_after'           => $r['status_after']->value,
                    'technician_notes'       => $r['notes'],
                    'before_photo_paths'     => json_encode($beforePhotoPaths),
                    'after_photo_paths'      => json_encode($afterPhotoPaths),
                    'created_at'             => now(),
                ];
            }

            // Bulk insert — one append-only row per affected asset (hot path, §7).
            if (! empty($historyRows)) {
                DB::table('service_history')->insert($historyRows);
            }

            $this->jobTransitionService->transitionTo($job, JobStatus::Validated, $actor);
        });

        WriteAuditLog::dispatch(
            userId: $actor->id,
            userRole: $actor->role->value,
            action: 'job.validated',
            auditableType: 'App\\Models\\ServiceJob',
            auditableId: $job->id,
            before: ['job_status' => JobStatus::Completed->value],
            after: [
                'job_status'  => JobStatus::Validated->value,
                'asset_count' => count($resolved),
                'asset_ids'   => array_keys($resolved),
            ],
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
        );
    }

    /**
     * Flag a Completed job as requiring remediation and spawn the remediation
     * sub-job (max depth 2, max 1 remediation per sub-job — enforced here, not
     * just in the create-job Form Request, since this is a separate entry point).
     *
     * @throws \InvalidArgumentException on depth/single-remediation violation
     */
    public function flagRemediation(ServiceJob $job, User $actor, string $reason): ServiceJob
    {
        $this->assertSameClientScope($job);

        if ($job->isLeaf()) {
            throw new \InvalidArgumentException('A remediation job cannot itself be flagged for remediation (maximum depth is 2).');
        }

        if ($job->hasRemediation()) {
            throw new \InvalidArgumentException('This job already has a remediation sub-job. Only one remediation is permitted per job.');
        }

        $job->loadMissing('assets');

        // Remediation jobs are always fault-type — start their own SLA clock (US-12.2).
        $client    = Client::with('slaProfile')->findOrFail($job->client_id);
        $store     = Store::findOrFail($job->store_id);
        $slaFields = $this->slaClockService->resolveClockFields($client, $store);

        $remediation = DB::transaction(function () use ($job, $actor, $reason, $slaFields): ServiceJob {
            $remediation = ServiceJob::create([
                'job_reference'   => $this->generateRemediationReference($job),
                'job_name'        => 'Remediation: '.$job->job_name,
                'job_description' => "Remediation required for job {$job->job_reference}.\n\n".
                    "Reason: {$reason}\n\nOriginal scope:\n{$job->job_description}",
                'job_type'           => JobType::FaultRepair->value,
                'client_id'          => $job->client_id,
                'store_id'           => $job->store_id,
                'job_timezone'       => $job->job_timezone,
                'early_start_window' => $job->early_start_window->value,
                'job_status'         => JobStatus::Draft->value,
                'parent_job_id'      => $job->id,
                'job_level'          => $job->job_level + 1,
                'client_email'       => $job->client_email,
                'client_name'        => $job->client_name,
                ...$slaFields,
            ]);

            $remediation->assets()->sync($job->assets->pluck('id')->all());

            $this->jobTransitionService->transitionTo($job, JobStatus::RequiresRemediation, $actor, $reason);

            return $remediation;
        });

        WriteAuditLog::dispatch(
            userId: $actor->id,
            userRole: $actor->role->value,
            action: 'job.remediation_flagged',
            auditableType: 'App\\Models\\ServiceJob',
            auditableId: $job->id,
            before: null,
            after: [
                'remediation_job_id' => $remediation->id,
                'reason'             => $reason,
            ],
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
        );

        return $remediation;
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Defence in depth: the job's affected assets must share its client_id.
     * Should be structurally guaranteed at attach time (US-08.2) — this re-verifies.
     */
    private function assertSameClientScope(ServiceJob $job): void
    {
        $mismatched = $job->assets->firstWhere('client_id', '!=', $job->client_id);

        if ($mismatched !== null) {
            throw new \InvalidArgumentException('One or more affected assets belong to a different client than this job.');
        }
    }

    private function resolveServiceDate(ServiceJob $job): Carbon
    {
        $latestEnd = JobCheckpoint::where('job_id', $job->id)
            ->whereNotNull('end_timestamp_utc')
            ->max('end_timestamp_utc');

        if ($latestEnd) {
            return Carbon::parse($latestEnd);
        }

        return $job->scheduled_date ?? now();
    }

    /** @return list<string> */
    private function photoPaths(ServiceJob $job, PhotoType $type): array
    {
        return JobPhoto::where('job_id', $job->id)
            ->where('type', $type->value)
            ->pluck('stored_path')
            ->values()
            ->all();
    }

    private function generateRemediationReference(ServiceJob $job): string
    {
        $base = $job->job_reference.'-REM';
        $ref  = $base;
        $n    = 1;

        while (ServiceJob::withTrashed()->where('job_reference', $ref)->exists()) {
            $ref = $base.'-'.(++$n);
        }

        return $ref;
    }
}
