<?php

namespace App\Models;

use App\Enums\PhotoType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPhoto extends BaseModel
{
    protected $table = 'job_photos';

    protected $fillable = [
        'job_id',
        'technician_profile_id',
        'type',
        'stored_path',
        'mime_type',
        'file_size',
        'client_upload_id',
    ];

    protected function casts(): array
    {
        return ['type' => PhotoType::class];
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
