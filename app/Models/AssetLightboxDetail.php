<?php

namespace App\Models;

use App\Enums\ContentChangeFrequency;
use App\Enums\LightType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetLightboxDetail extends BaseModel
{
    protected $fillable = [
        'asset_id',
        'lightbox_dimensions',
        'light_type',
        'content_change_frequency',
    ];

    protected function casts(): array
    {
        return [
            'light_type'               => LightType::class,
            'content_change_frequency' => ContentChangeFrequency::class,
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
