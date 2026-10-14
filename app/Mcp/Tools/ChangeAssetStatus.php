<?php

namespace App\Mcp\Tools;

use App\Enums\AgentAbility;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Services\AssetTransitionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('change_asset_status')]
#[Description('Change an asset\'s status through the asset lifecycle (e.g. active → faulty). Only permitted transitions are allowed; decommissioned is final. A reason is required and recorded in the asset history. Run with dry_run: true first.')]
class ChangeAssetStatus extends AgentTool
{
    protected function ability(): AgentAbility
    {
        return AgentAbility::AssetsWrite;
    }

    public function handle(Request $request, AssetTransitionService $transitions): Response
    {
        $agent = $this->agent($request);

        $input = $request->validate([
            'asset_code' => ['required', 'string', 'max:40'],
            'status'     => ['required', Rule::enum(AssetStatus::class)->except(AssetStatus::PendingVerification)],
            'reason'     => ['required', 'string', 'max:500'],
        ], [
            'reason.required' => 'Say why the status is changing, e.g. "Seen faulty on site visit 12 Oct 2026".',
        ]);

        $asset = Asset::where('asset_code', $input['asset_code'])->first();

        if ($asset === null) {
            return Response::error("No asset with code {$input['asset_code']} is visible to this access key.");
        }

        $this->authorizeFor($agent, 'update', $asset);

        $to = AssetStatus::from($input['status']);

        if (! $transitions->isPermitted($asset->asset_status, $to)) {
            $allowed = implode(', ', array_map(fn (AssetStatus $s) => $s->value, $transitions->permittedTransitionsFrom($asset->asset_status)));

            return Response::error("{$asset->asset_code} cannot go from {$asset->asset_status->value} to {$to->value}. Allowed: ".($allowed ?: 'none (final status)').'.');
        }

        $from = $asset->asset_status->value;

        if ($request->boolean('dry_run')) {
            return $this->dryRunResponse("change {$asset->asset_code} from {$from} to {$to->value}", ['reason' => $input['reason']]);
        }

        $transitions->transitionTo($asset, $to, actor: $agent, reason: $input['reason']);

        return Response::json(['asset_code' => $asset->asset_code, 'from' => $from, 'to' => $to->value]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'asset_code' => $schema->string()->required(),
            'status'     => $schema->string()->enum(array_column(AssetStatus::selectable(), 'value'))->required(),
            'reason'     => $schema->string()->description('Why, in plain words. Recorded in the asset history.')->required(),
            'dry_run'    => $schema->boolean()->description('true = check the transition without saving.'),
        ];
    }
}
