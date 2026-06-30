<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobAttachment extends BaseModel
{
    protected $table = 'job_attachments';

    protected $fillable = [
        'job_id',
        'original_filename',
        'stored_path',
        'mime_type',
        'file_size',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class, 'job_id');
    }
}
