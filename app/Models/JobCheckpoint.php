<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $job_id
 * @property int $technician_profile_id
 * @property Carbon|null $start_timestamp_utc
 * @property Carbon|null $end_timestamp_utc
 */
class JobCheckpoint extends BaseModel
{
    protected $table = 'job_checkpoints';

    protected $fillable = [
        'job_id',
        'technician_profile_id',
        'start_timestamp_utc',
        'start_lat',
        'start_lng',
        'start_gps_status',
        'end_timestamp_utc',
        'end_lat',
        'end_lng',
        'end_gps_status',
        'completion_notes',
    ];

    protected function casts(): array
    {
        return [
            'start_timestamp_utc' => 'datetime',
            'end_timestamp_utc'   => 'datetime',
            'start_lat'           => 'float',
            'start_lng'           => 'float',
            'end_lat'             => 'float',
            'end_lng'             => 'float',
        ];
    }

    /** @return BelongsTo<ServiceJob, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class, 'job_id');
    }

    /** @return BelongsTo<TechnicianProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(TechnicianProfile::class, 'technician_profile_id');
    }
}
