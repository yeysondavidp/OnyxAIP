<?php

namespace App\Models;

use App\Enums\AustralianState;
use App\Enums\StoreType;
use App\Traits\Auditable;
use App\Traits\ClientScoped;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Store extends BaseModel
{
    /** @use HasFactory<StoreFactory> */
    use Auditable, ClientScoped, HasFactory;

    protected $fillable = [
        'client_id',
        'store_name',
        'store_code',
        'store_type',
        'address_line1',
        'suburb',
        'state',
        'postcode',
        'country',
        'store_timezone',
        'store_manager_name',
        'store_manager_phone',
        'store_manager_email',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'store_type' => StoreType::class,
            'state'      => AustralianState::class,
            'is_active'  => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
