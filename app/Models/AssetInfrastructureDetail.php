<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetInfrastructureDetail extends BaseModel
{
    protected $fillable = [
        'asset_id',
        'cable_type',
        'length',
        'connected_from_asset_id',
        'connected_to_asset_id',
    ];

    protected function casts(): array
    {
        return [
            'length'                  => 'decimal:2',
            'connected_from_asset_id' => 'integer',
            'connected_to_asset_id'   => 'integer',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function connectedFrom(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'connected_from_asset_id');
    }

    public function connectedTo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'connected_to_asset_id');
    }
}
