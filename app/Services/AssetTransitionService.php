<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Jobs\WriteAuditLog;
use App\Models\Asset;
use App\Models\AssetHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for all asset status transitions (US-06.1/06.2, SRA §4.5).
 *
 * Every caller — PM UI, job auto-transitions, future automated monitoring — MUST
 * go through transitionTo(). Direct writes to asset_status are not permitted.
 *
 * Permitted transitions (§4.5):
 *   Active           → Faulty, Offline, Decommissioned
 *   Faulty           → UnderMaintenance, Decommissioned
 *   Offline          → Active, Decommissioned
 *   UnderMaintenance → Active, Decommissioned
 *   Decommissioned   → (none — terminal)
 *
 * Actor may be:
 *   - A User model (PM acting via UI)
 *   - null + $systemLabel string (system/job auto-transition, e.g. "system:job_created")
 */
class AssetTransitionService
{
    /** @var array<string, list<string>> permitted target statuses keyed by current status value */
    private const PERMITTED = [
        'active'            => ['faulty', 'offline', 'decommissioned'],
        'faulty'            => ['under_maintenance', 'decommissioned'],
        'offline'           => ['active', 'decommissioned'],
        'under_maintenance' => ['active', 'decommissioned'],
        'decommissioned'    => [],
    ];

    /**
     * Attempt to transition an asset to a new status.
     *
     * @throws \InvalidArgumentException if the transition is not permitted
     */
    public function transitionTo(
        Asset $asset,
        AssetStatus $newStatus,
        ?User $actor = null,
        ?string $systemLabel = null,
        ?string $reason = null,
    ): void {
        $current = $asset->asset_status;

        if (! $this->isPermitted($current, $newStatus)) {
            throw new \InvalidArgumentException(
                "Transition from [{$current->value}] to [{$newStatus->value}] is not permitted."
            );
        }

        if ($current === $newStatus) {
            return; // no-op — already in target state
        }

        DB::transaction(function () use ($asset, $current, $newStatus, $actor, $systemLabel, $reason): void {
            // Direct DB update — intentional, bypasses mass-assignment fillable guard
            DB::table('assets')
                ->where('id', $asset->id)
                ->update(['asset_status' => $newStatus->value, 'updated_at' => now()]);

            $asset->asset_status = $newStatus;

            $actorId    = $actor?->id;
            $actorRole  = $actor?->role?->value;
            $actorLabel = $systemLabel ?? ($actor ? null : 'system');

            // In-band history record (append-only)
            AssetHistory::create([
                'asset_id'        => $asset->id,
                'status_before'   => $current->value,
                'status_after'    => $newStatus->value,
                'actor_id'        => $actorId,
                'actor_role'      => $actorRole,
                'actor_label'     => $actorLabel,
                'reason'          => $reason,
                'transitioned_at' => now(),
            ]);
        });

        // Async audit log outside the transaction (non-blocking)
        WriteAuditLog::dispatch(
            userId: $actor?->id,
            userRole: $actor?->role?->value,
            action: 'status_changed',
            auditableType: 'App\\Models\\Asset',
            auditableId: $asset->id,
            before: ['asset_status' => $current->value],
            after: ['asset_status' => $newStatus->value, 'reason' => $reason],
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
        );
    }

    public function isPermitted(AssetStatus $from, AssetStatus $to): bool
    {
        return in_array($to->value, self::PERMITTED[$from->value], strict: true);
    }

    /** @return list<AssetStatus> */
    public function permittedTransitionsFrom(AssetStatus $status): array
    {
        return array_map(
            fn (string $v) => AssetStatus::from($v),
            self::PERMITTED[$status->value]
        );
    }
}
