<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Client is the tenant root — it does NOT use ClientScoped (it IS the client).
 * All other tenant-scoped models belong to a Client via client_id.
 */
class Client extends BaseModel
{
    use Auditable;

    protected $fillable = [
        'name',
        'client_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }
}
