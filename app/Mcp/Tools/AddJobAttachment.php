<?php

namespace App\Mcp\Tools;

use App\Enums\AgentAbility;
use App\Http\Requests\UploadJobAttachmentRequest;
use App\Models\ServiceJob;
use App\Services\JobAttachmentService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\UploadedFile;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('add_job_attachment')]
#[Description('Upload an evidence file (PDF report, photo, Word or Excel document) to a service job as a PM attachment, with the same file-type checks as the job page. Send the file base64-encoded, at most 15 MB; larger files must be uploaded on the job page. Run with dry_run: true first.')]
class AddJobAttachment extends AgentTool
{
    /** Base64 inflates ~33% and the server accepts 25 MB request bodies. */
    public const MAX_FILE_KB = 15360;

    protected function ability(): AgentAbility
    {
        return AgentAbility::AttachmentsWrite;
    }

    public function handle(Request $request, JobAttachmentService $attachments): Response
    {
        $agent = $this->agent($request);

        $input = $request->validate([
            'job_reference'  => ['required', 'string', 'max:80'],
            'filename'       => ['required', 'string', 'max:255', 'regex:/^[^\/\\\\]+$/'],
            'content_base64' => ['required', 'string'],
        ], [
            'filename.regex' => 'The filename must not contain folders.',
        ]);

        $job = ServiceJob::where('job_reference', $input['job_reference'])->first();

        if ($job === null) {
            return Response::error("No job with reference {$input['job_reference']} is visible to this access key.");
        }

        $this->authorizeFor($agent, 'addAttachment', $job);

        $bytes = base64_decode($input['content_base64'], strict: true);

        if ($bytes === false || $bytes === '') {
            return Response::error('content_base64 is not valid base64.');
        }

        if (strlen($bytes) > self::MAX_FILE_KB * 1024) {
            return Response::error('The file is larger than 15 MB. Upload it on the job page instead.');
        }

        $path = tempnam(sys_get_temp_dir(), 'mcp-attachment-');

        if ($path === false) {
            throw new \RuntimeException('Could not create a temporary file for the upload.');
        }

        try {
            file_put_contents($path, $bytes);
            $file = new UploadedFile($path, $input['filename'], null, null, test: true);

            $this->validateWith(UploadJobAttachmentRequest::class, ['attachment' => $file], ['job' => $job]);
            $mime = $attachments->detectAllowedMime($file);

            if ($request->boolean('dry_run')) {
                return $this->dryRunResponse("attach {$input['filename']} to {$job->job_reference}", ['mime_type' => $mime, 'file_size' => strlen($bytes)]);
            }

            $attachment = $attachments->store($job, $file, $input['filename']);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        return Response::json(['attached' => [
            'job_reference' => $job->job_reference,
            'filename'      => $attachment->original_filename,
            'mime_type'     => $attachment->mime_type,
            'file_size'     => $attachment->file_size,
        ]]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'job_reference'  => $schema->string()->required(),
            'filename'       => $schema->string()->description('e.g. "Technical_Service_Report_Pandora_Miranda_12Oct2026.pdf".')->required(),
            'content_base64' => $schema->string()->description('The file content, base64-encoded.')->required(),
            'dry_run'        => $schema->boolean()->description('true = check the file without saving.'),
        ];
    }
}
