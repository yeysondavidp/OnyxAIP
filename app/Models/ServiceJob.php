<?php

namespace App\Models;

use App\Enums\EarlyStartWindow;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Enums\SlaStatus;
use App\Enums\TechnicianJobStatus;
use App\Services\JobTransitionService;
use App\Services\Sla\SlaClockService;
use App\Traits\Auditable;
use App\Traits\ClientScoped;
use Database\Factories\ServiceJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property JobStatus $job_status
 * @property JobType $job_type
 * @property EarlyStartWindow $early_start_window
 * @property Carbon|null $sla_clock_started_at
 * @property Carbon|null $sla_resolution_target_at
 * @property Carbon|null $sla_at_risk_at
 */
class ServiceJob extends BaseModel
{
    /** @use HasFactory<ServiceJobFactory> */
    use Auditable, ClientScoped, HasFactory, SoftDeletes;

    protected $table = 'service_jobs';

    protected $fillable = [
        'job_reference',
        'job_name',
        'job_description',
        'job_type',
        'client_id',
        'store_id',
        'job_timezone',
        'scheduled_date',
        'scheduled_time',
        'early_start_window',
        'job_status',
        'parent_job_id',
        'job_level',
        'client_email',
        'client_name',
        'sla_profile_id',
        'sla_clock_started_at',
        'sla_resolution_target_at',
        'sla_at_risk_at',
        'sla_at_risk',
        'sla_breached',
        'force_complete_reason',
    ];

    protected function casts(): array
    {
        return [
            'job_type'                 => JobType::class,
            'job_status'               => JobStatus::class,
            'early_start_window'       => EarlyStartWindow::class,
            'scheduled_date'           => 'date',
            'sla_clock_started_at'     => 'datetime',
            'sla_resolution_target_at' => 'datetime',
            'sla_at_risk_at'           => 'datetime',
            'sla_at_risk'              => 'boolean',
            'sla_breached'             => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** Snapshot of the profile in effect when the SLA clock started (US-12.2). */
    public function slaProfile(): BelongsTo
    {
        return $this->belongsTo(SlaProfile::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class, 'parent_job_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ServiceJob::class, 'parent_job_id');
    }

    /** Affected assets for this job (US-08.2). status_before is captured at attach time (US-11.1). */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'job_assets', 'job_id', 'asset_id')
            ->withPivot('status_before');
    }

    /** Assigned technicians with their per-technician lifecycle (US-08.4/09.1). */
    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(
            TechnicianProfile::class,
            'job_technicians',
            'job_id',
            'technician_profile_id'
        )->withPivot('technician_status', 'invitation_token', 'token_expires_at', 'force_complete_reason');
    }

    /** PM attachments (US-08.6). */
    public function attachments(): HasMany
    {
        return $this->hasMany(JobAttachment::class, 'job_id');
    }

    // ── State machine ──────────────────────────────────────────────────────────

    /**
     * Transition to a new status via the centralised guard (US-08.3).
     *
     * @throws \InvalidArgumentException when the transition is not permitted
     */
    public function transitionTo(JobStatus $newStatus, ?User $actor = null, ?string $reason = null): void
    {
        app(JobTransitionService::class)->transitionTo($this, $newStatus, $actor, $reason);
    }

    // ── SLA (US-12.3) ──────────────────────────────────────────────────────────

    /** Pure read of the stored SLA columns — no live computation on hot paths. */
    public function slaStatus(): SlaStatus
    {
        return SlaClockService::statusFor(
            $this->sla_clock_started_at?->toIso8601String(),
            $this->sla_at_risk,
            $this->sla_breached,
        );
    }

    // ── Hierarchy helpers ──────────────────────────────────────────────────────

    /** True when this job is a standalone or campaign-root (no parent). */
    public function isRoot(): bool
    {
        return $this->parent_job_id === null;
    }

    /** True when this job has reached max depth (no further children allowed). */
    public function isLeaf(): bool
    {
        return $this->job_level >= 2;
    }

    /** True when a remediation child already exists (max 1 per sub-job). */
    public function hasRemediation(): bool
    {
        return $this->children()->exists();
    }

    // ── Multi-technician roll-up helpers (US-08.4) ─────────────────────────────

    /**
     * Returns true when all assigned technicians have submitted (status = completed).
     */
    public function allTechniciansCompleted(): bool
    {
        $total = $this->technicians()->count();
        if ($total === 0) {
            return false;
        }

        $completed = $this->technicians()
            ->wherePivot('technician_status', TechnicianJobStatus::Completed->value)
            ->count();

        return $completed === $total;
    }
}
