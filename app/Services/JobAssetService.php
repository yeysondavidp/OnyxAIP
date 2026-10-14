<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\ServiceJob;
use Illuminate\Support\Facades\DB;

/**
 * Affected assets on a service job (US-08.2) — shared by the job form and the
 * agent MCP tools (US-20.3) so both apply exactly the same side effects.
 */
class JobAssetService
{
    public function __construct(private readonly AssetTransitionService $transitions) {}

    /**
     * Sync the affected-assets pivot and auto-transition eligible assets
     * to UnderMaintenance via the AssetTransitionService (US-08.2).
     *
     * Captures each asset's status_before on the pivot at attach time — the
     * reference point US-11.1 needs at validation, taken before the auto-
     * transition below. Preserved across re-syncs on edit (never overwritten
     * once set) so re-saving the job form doesn't lose the original value.
     *
     * @param  list<int>  $assetIds
     */
    public function sync(ServiceJob $job, array $assetIds): void
    {
        $existingStatusBefore = $job->assets()->pluck('job_assets.status_before', 'assets.id')->toArray();

        // Devices found on site are hidden from the job form until verified (EPIC-21) — keep
        // them attached instead of letting a form save detach them.
        $pendingSync = Asset::pendingDevices()
            ->whereIn('id', DB::table('job_assets')->where('job_id', $job->id)->pluck('asset_id'))
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [(int) $id => ['status_before' => AssetStatus::PendingVerification->value]])
            ->all();

        if (empty($assetIds)) {
            $job->assets()->sync($pendingSync);

            return;
        }

        $assets = Asset::whereIn('id', $assetIds)->get(['id', 'asset_status']);

        $syncData = [];
        foreach ($assets as $asset) {
            $syncData[$asset->id] = [
                'status_before' => $existingStatusBefore[$asset->id] ?? $asset->asset_status->value,
            ];
        }

        $job->assets()->sync($syncData + $pendingSync);

        $assets
            ->filter(fn (Asset $a) => in_array($a->asset_status, [AssetStatus::Active, AssetStatus::Faulty], strict: true))
            ->each(function (Asset $asset): void {
                try {
                    $this->transitions->transitionTo(
                        $asset,
                        AssetStatus::UnderMaintenance,
                        systemLabel: 'system:job_created'
                    );
                } catch (\InvalidArgumentException) {
                    // Already Under Maintenance or ineligible — skip silently
                }
            });
    }

    /**
     * Add assets to a job without detaching any already on it.
     *
     * @param  list<int>  $assetIds
     */
    public function attach(ServiceJob $job, array $assetIds): void
    {
        $existing = $job->assets()->pluck('assets.id')->map(fn ($id) => (int) $id)->all();

        $this->sync($job, array_values(array_unique([...$existing, ...$assetIds])));
    }
}
