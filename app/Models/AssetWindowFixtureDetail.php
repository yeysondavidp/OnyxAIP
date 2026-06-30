<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetWindowFixtureDetail extends BaseModel
{
    protected $fillable = [
        'asset_id',
        'fixture_dimensions',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
