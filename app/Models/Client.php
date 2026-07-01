<?php

namespace App\Models;

use App\Traits\Auditable;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Client is the tenant root — it does NOT use ClientScoped (it IS the client).
 * All other tenant-scoped models belong to a Client via client_id.
 */
class Client extends BaseModel
{
    /** @use HasFactory<ClientFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'client_name',
        'client_code',
        'primary_contact',
        'primary_email',
        'notes',
        'sla_profile_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'sla_profile_id' => 'integer',
        ];
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    /** @return BelongsTo<SlaProfile, $this> */
    public function slaProfile(): BelongsTo
    {
        return $this->belongsTo(SlaProfile::class);
    }
}
