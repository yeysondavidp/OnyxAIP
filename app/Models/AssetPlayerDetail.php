<?php

namespace App\Models;

use App\Enums\PlayerType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetPlayerDetail extends BaseModel
{
    protected $fillable = [
        'asset_id',
        'player_type',
        'cms_platform',
        'ip_address',
        'mac_address',
        'firmware_version',
    ];

    protected function casts(): array
    {
        return [
            'player_type' => PlayerType::class,
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
