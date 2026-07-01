<?php

namespace App\Models;

use App\Enums\AssetStatus;
use App\Enums\JobType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only per-asset service record written on job validation (US-11.1, SRA §7).
 *
 * Never updated or deleted at the application layer — same pattern as AssetHistory
 * and the audit log (US-00.5). Only ServiceHistory::create() may add rows; this
 * model is the single writer contract reused by reporting (EPIC-14).
 */
class ServiceHistory extends Model
{
    protected $table = 'service_history';

    public $timestamps = false;

    protected $fillable = [
        'asset_id',
        'service_job_id',
        'service_date',
        'technician_profile_ids',
        'job_type',
        'status_before',
        'status_after',
        'technician_notes',
        'before_photo_paths',
        'after_photo_paths',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'service_date'           => 'date',
            'technician_profile_ids' => 'array',
            'job_type'               => JobType::class,
            'status_before'          => AssetStatus::class,
            'status_after'           => AssetStatus::class,
            'before_photo_paths'     => 'array',
            'after_photo_paths'      => 'array',
            'created_at'             => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function serviceJob(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class, 'service_job_id');
    }

    /** @return Collection<int, TechnicianProfile> */
    public function technicianProfiles(): Collection
    {
        return TechnicianProfile::whereIn('id', $this->technician_profile_ids ?? [])->get();
    }

    /**
     * Shared aggregation path for a store's service history (US-11.4).
     * Reused by the store-level Livewire view and, later, EPIC-14 reporting —
     * one query path, not a reporting-only re-implementation.
     *
     * @param  array{asset_type?: string, from?: string, to?: string}  $filters
     * @return Builder<ServiceHistory>
     */
    public static function forStore(int $storeId, array $filters = []): Builder
    {
        return self::query()
            ->whereHas('asset', fn ($q) => $q->where('store_id', $storeId))
            ->when(
                $filters['asset_type'] ?? null,
                fn (Builder $q, string $type) => $q->whereHas('asset', fn ($a) => $a->where('asset_type', $type))
            )
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->where('service_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->where('service_date', '<=', $to))
            ->with(['asset', 'serviceJob'])
            ->orderByDesc('service_date')
            ->orderByDesc('id');
    }

    // ── Append-only guard (US-00.5 pattern) ────────────────────────────────────

    public function save(array $options = []): bool
    {
        if (! $this->exists) {
            return parent::save($options);
        }

        return false;
    }

    public function delete(): ?bool
    {
        return false;
    }
}
