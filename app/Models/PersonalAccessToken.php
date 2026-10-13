<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum token with the PM who issued it (US-20.1). Not Auditable: Sanctum
 * writes last_used_at on every request, which would flood the audit log —
 * issuance and revocation are audited explicitly by AgentAccessService.
 *
 * @property int|null $created_by_user_id
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'created_by_user_id',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
