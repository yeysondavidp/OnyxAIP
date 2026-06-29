<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\ClientScoped;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Store extends BaseModel
{
    use Auditable, ClientScoped;

    protected $fillable = [
        'client_id',
        'name',
        'store_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
