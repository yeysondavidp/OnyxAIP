<?php

namespace App\Models;

use App\Enums\Orientation;
use App\Enums\TotemSuppliedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetScreenDetail extends BaseModel
{
    protected $fillable = [
        'asset_id',
        'screen_size_inches',
        'resolution_width',
        'resolution_height',
        'orientation',
        'mount_type',
        'totem_supplied_by',
    ];

    protected function casts(): array
    {
        return [
            'screen_size_inches' => 'decimal:1',
            'resolution_width'   => 'integer',
            'resolution_height'  => 'integer',
            'orientation'        => Orientation::class,
            'totem_supplied_by'  => TotemSuppliedBy::class,
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
