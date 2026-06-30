<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function job(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class, 'job_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(TechnicianProfile::class, 'technician_profile_id');
    }
}
