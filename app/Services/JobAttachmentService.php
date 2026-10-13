<?php

namespace App\Services;

use App\Http\Requests\UploadJobAttachmentRequest;
use App\Models\JobAttachment;
use App\Models\ServiceJob;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * PM attachments on a service job (US-08.6) — shared by the job page and the
 * agent MCP tools (US-20.3): one MIME check, one storage path, one record shape.
 */
class JobAttachmentService
{
    /**
     * Detect the MIME type server-side — never trust the client-supplied type.
     *
     * @throws ValidationException when the content is not on the allow-list
     */
    public function detectAllowedMime(File|UploadedFile $file): string
    {
        $mime = $file->getMimeType() ?? '';

        if (! in_array($mime, UploadJobAttachmentRequest::ALLOWED_MIMES, strict: true)) {
            throw ValidationException::withMessages(['attachment' => 'File type is not permitted.']);
        }

        return $mime;
    }

    /** @throws ValidationException */
    public function store(ServiceJob $job, File|UploadedFile $file, string $originalFilename): JobAttachment
    {
        $mime = $this->detectAllowedMime($file);

        $path = Storage::disk('local')->putFile('job-attachments/'.$job->id, $file);

        return JobAttachment::create([
            'job_id'            => $job->id,
            'original_filename' => $originalFilename,
            'stored_path'       => (string) $path,
            'mime_type'         => $mime,
            'file_size'         => $file->getSize() ?: null,
        ]);
    }
}
