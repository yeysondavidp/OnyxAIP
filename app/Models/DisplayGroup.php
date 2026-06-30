<?php

namespace App\Models;

use App\Traits\Auditable;
use Database\Factories\DisplayGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DisplayGroup extends BaseModel
{
    /** @use HasFactory<DisplayGroupFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'group_name',
        'player_asset_id',
        'layout_description',
        'notes',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'player_asset_id');
    }

    public function screens(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'display_group_screens', 'display_group_id', 'asset_id');
    }
}
