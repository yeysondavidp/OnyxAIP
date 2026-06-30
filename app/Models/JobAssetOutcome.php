<?php

namespace App\Models;

use App\Enums\PostServiceStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobAssetOutcome extends BaseModel
{
    protected $table = 'job_asset_outcomes';

    protected $fillable = [
        'job_id',
        'asset_id',
        'post_service_status',
        'technician_notes',
    ];

    protected function casts(): array
    {
        return ['post_service_status' => PostServiceStatus::class];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class, 'job_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
