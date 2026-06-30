<?php

namespace App\Models;

use App\Enums\AssetStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only chronological log of every asset status transition.
 * Never updated or deleted at the application layer (§14.5).
 */
class AssetHistory extends Model
{
    protected $table = 'asset_history';

    public $timestamps = false;

    protected $fillable = [
        'asset_id',
        'status_before',
        'status_after',
        'actor_id',
        'actor_role',
        'actor_label',
        'reason',
        'transitioned_at',
    ];

    protected function casts(): array
    {
        return [
            'status_before'   => AssetStatus::class,
            'status_after'    => AssetStatus::class,
            'transitioned_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    // Prevent updates — history is append-only.
    public function save(array $options = []): bool
    {
        if (! $this->exists) {
            return parent::save($options);
        }

        return false;
    }

    public function delete(): ?bool
    {
        return false;
    }
}
