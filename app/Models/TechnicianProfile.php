<?php

namespace App\Models;

use App\Enums\TechnicianSpecialty;
use App\Traits\Auditable;
use Database\Factories\TechnicianProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechnicianProfile extends BaseModel
{
    /** @use HasFactory<TechnicianProfileFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'specialty_categories',
        'certifications',
        'preferred_client_ids',
        'asset_competency',
        'is_active',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'specialty_categories' => 'array',
            'certifications'       => 'array',
            'preferred_client_ids' => 'array',
            'is_active'            => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    /** Linked technician user account (optional — null for guest profiles). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Jobs this technician has been assigned to. */
    public function jobs(): BelongsToMany
    {
        return $this->belongsToMany(
            ServiceJob::class,
            'job_technicians',
            'technician_profile_id',
            'job_id'
        )->withPivot('technician_status', 'invitation_token', 'token_expires_at', 'force_complete_reason');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /** @return list<TechnicianSpecialty> */
    public function specialtyEnums(): array
    {
        return array_filter(
            array_map(
                fn (string $v) => TechnicianSpecialty::tryFrom($v),
                $this->specialty_categories ?? []
            )
        );
    }

    /** @return list<string> */
    public function specialtyLabels(): array
    {
        return array_map(fn (TechnicianSpecialty $s) => $s->label(), $this->specialtyEnums());
    }

    /** True when this profile has a linked technician user account. */
    public function hasAccount(): bool
    {
        return $this->user_id !== null;
    }
}
