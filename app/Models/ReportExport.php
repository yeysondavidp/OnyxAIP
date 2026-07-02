<?php

namespace App\Models;

use App\Enums\ReportExportStatus;
use App\Enums\ReportFormat;
use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tracks a single generated report file (EPIC-14) — the row a signed download
 * URL resolves to, and what the daily reports:prune command cleans up once
 * expires_at has passed. Not ClientScoped: client_id is nullable (Technician
 * Hours has no client dimension) and every report query applies its own
 * explicit client_id filter rather than relying on the global scope (see
 * ReportService — the scope is a no-op in a queued job's context anyway).
 *
 * @property ReportType $report_type
 * @property ReportFormat $format
 * @property ReportExportStatus $status
 * @property array<string, mixed> $params
 * @property int|null $client_id
 * @property string|null $path
 * @property Carbon $expires_at
 */
class ReportExport extends BaseModel
{
    /** Not persisted — set by ReportsIndexController for the recent-exports list. */
    public ?string $downloadUrl = null;

    protected $fillable = [
        'report_type',
        'format',
        'client_id',
        'requested_by_user_id',
        'params',
        'status',
        'disk',
        'path',
        'row_count',
        'failure_reason',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'report_type' => ReportType::class,
            'format'      => ReportFormat::class,
            'status'      => ReportExportStatus::class,
            'params'      => 'array',
            'expires_at'  => 'datetime',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** Recent-exports list: everything the acting user is permitted to see. */
    public function scopePermittedFor(Builder $query, User $user): Builder
    {
        $permitted = $user->permittedClientIds();

        if ($permitted === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($permitted) {
            $q->whereNull('client_id')->orWhereIn('client_id', $permitted);
        });
    }
}
