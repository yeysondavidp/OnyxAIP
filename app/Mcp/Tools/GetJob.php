<?php

namespace App\Mcp\Tools;

use App\Enums\AgentAbility;
use App\Models\Asset;
use App\Models\JobAttachment;
use App\Models\ServiceJob;
use App\Models\TechnicianProfile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get_job')]
#[Description('Get one service job by job reference (e.g. JOB-2026-004): status, store, schedule, affected assets, assigned technicians and attachment names. Never returns technician links, file paths or secrets.')]
class GetJob extends AgentTool
{
    protected function ability(): AgentAbility
    {
        return AgentAbility::RegisterRead;
    }

    public function handle(Request $request): Response
    {
        $agent = $this->agent($request);

        $input = $request->validate(['job_reference' => ['required', 'string', 'max:40']]);

        $job = ServiceJob::with([
            'store:id,store_code,store_name',
            'assets' => fn ($q) => $q->with(['store:id,store_code', 'playerDetail']),
            'technicians',
            'attachments',
        ])->where('job_reference', $input['job_reference'])->first();

        if ($job === null) {
            return Response::error("No job with reference {$input['job_reference']} is visible to this access key.");
        }

        $this->authorizeFor($agent, 'view', $job);

        return Response::json([
            'job' => [
                'job_reference'   => $job->job_reference,
                'job_name'        => $job->job_name,
                'job_type'        => $job->job_type->value,
                'job_status'      => $job->job_status->value,
                'job_description' => $job->job_description,
                'store_code'      => $job->store?->store_code,
                'store_name'      => $job->store?->store_name,
                'scheduled_date'  => $job->scheduled_date?->toDateString(),
                'scheduled_time'  => $job->scheduled_time,
                'job_timezone'    => $job->job_timezone,
            ],
            'assets' => $job->assets->map(fn (Asset $a) => self::assetSummary($a) + [
                'status_before' => $a->getRelationValue('pivot')?->getAttribute('status_before'),
            ])->values(),
            'technicians' => $job->technicians->map(fn (TechnicianProfile $t) => [
                'name'   => $t->name,
                'status' => $t->getRelationValue('pivot')?->getAttribute('technician_status'),
            ])->values(),
            'attachments' => $job->attachments->map(fn (JobAttachment $f) => [
                'filename'  => $f->original_filename,
                'mime_type' => $f->mime_type,
                'file_size' => $f->file_size,
            ])->values(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'job_reference' => $schema->string()->description('The job reference, e.g. "JOB-2026-004".')->required(),
        ];
    }
}
