<?php

namespace App\Http\Requests;

use App\Models\ServiceJob;
use Illuminate\Foundation\Http\FormRequest;

class UploadJobAttachmentRequest extends FormRequest
{
    /** Allowed MIME types for PM attachments (US-08.6, SRA §14.3). */
    public const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /** Corresponding extensions for the allow-list (double-checked with MIME). */
    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx'];

    /** 20 MB per file. */
    public const MAX_SIZE_KB = 20480;

    public function authorize(): bool
    {
        /** @var ServiceJob $job */
        $job = $this->route('job');

        return $this->user()->can('manageAttachments', $job);
    }

    public function rules(): array
    {
        $mimes = implode(',', self::ALLOWED_EXTENSIONS);

        return [
            'attachment' => [
                'required',
                'file',
                "mimes:{$mimes}",
                'max:'.self::MAX_SIZE_KB,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'attachment.mimes' => 'Only PDF, image (JPEG/PNG/GIF/WebP), Word, and Excel files are permitted.',
            'attachment.max'   => 'Attachments must be 20 MB or smaller.',
        ];
    }
}
