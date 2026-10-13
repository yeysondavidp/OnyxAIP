<?php

namespace App\Mcp\Tools;

use App\Enums\AgentAbility;
use App\Enums\AssetStatus;
use App\Enums\JobStatus;
use App\Models\Asset;
use App\Models\ServiceJob;
use App\Services\JobAssetService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('attach_assets_to_job')]
#[Description('Add affected assets to a service job, exactly as the job form does: each asset\'s current status is recorded and active or faulty assets move to under_maintenance. Assets must be at the job\'s store. Nothing already on the job is removed. Run with dry_run: true first.')]
class AttachAssetsToJob extends AgentTool
{
    /** A closed job's record is final (service history is append-only, SRA §7). */
    private const CLOSED_STATUSES = [JobStatus::Validated, JobStatus::Cancelled];

    protected function ability(): AgentAbility
    {
        return AgentAbility::JobsWrite;
    }

    public function handle(Request $request, JobAssetService $jobAssets): Response
    {
        $agent = $this->agent($request);

        $input = $request->validate([
            'job_reference' => ['required', 'string', 'max:80'],
            'asset_codes'   => ['required', 'array', 'min:1', 'max:'.self::MAX_RESULTS],
            'asset_codes.*' => ['string', 'distinct', 'max:40'],
        ]);

        $job = ServiceJob::with('assets:id')->where('job_reference', $input['job_reference'])->first();

        if ($job === null) {
            return Response::error("No job with reference {$input['job_reference']} is visible to this access key.");
        }

        $this->authorizeFor($agent, 'attachAssets', $job);

        if (in_array($job->job_status, self::CLOSED_STATUSES, true)) {
            return Response::error("{$job->job_reference} is {$job->job_status->value}; its assets can no longer change.");
        }

        $assets  = Asset::whereIn('asset_code', $input['asset_codes'])->where('store_id', $job->store_id)->get(['id', 'asset_code', 'asset_status']);
        $missing = array_values(array_diff($input['asset_codes'], $assets->pluck('asset_code')->all()));

        if ($missing !== []) {
            return Response::error('Not found at this job\'s store: '.implode(', ', $missing).'.');
        }

        $already = $job->assets->pluck('id')->all();
        $new     = $assets->reject(fn (Asset $a) => in_array($a->id, $already, true));

        $planned = [
            'add'                       => $new->pluck('asset_code')->values(),
            'already_on_job'            => $assets->diff($new)->pluck('asset_code')->values(),
            'will_be_under_maintenance' => $new->filter(fn (Asset $a) => in_array($a->asset_status, [AssetStatus::Active, AssetStatus::Faulty], true))->pluck('asset_code')->values(),
        ];

        if ($request->boolean('dry_run')) {
            return $this->dryRunResponse("add {$new->count()} asset(s) to {$job->job_reference}", $planned);
        }

        $jobAssets->attach($job, $new->pluck('id')->map(fn ($id) => (int) $id)->values()->all());

        return Response::json(['job_reference' => $job->job_reference] + $planned);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'job_reference' => $schema->string()->description('e.g. "JOB-2026-004".')->required(),
            'asset_codes'   => $schema->array()->items($schema->string())->description('Asset codes at the job\'s store.')->required(),
            'dry_run'       => $schema->boolean()->description('true = show what would change without saving.'),
        ];
    }
}
