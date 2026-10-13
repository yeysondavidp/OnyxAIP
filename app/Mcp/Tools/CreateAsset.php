<?php

namespace App\Mcp\Tools;

use App\Enums\AgentAbility;
use App\Http\Requests\CreateAssetRequest;
use App\Mcp\Tools\Concerns\AcceptsAssetFields;
use App\Models\Asset;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_asset')]
#[Description('Register a new asset at a store, with the same checks as the asset form (unique asset code, required type-specific fields). Check search_assets first so you do not register a device twice. Run with dry_run: true first.')]
class CreateAsset extends AgentTool
{
    use AcceptsAssetFields;

    protected function ability(): AgentAbility
    {
        return AgentAbility::AssetsWrite;
    }

    public function handle(Request $request): Response
    {
        $agent = $this->agent($request);
        $this->authorizeFor($agent, 'create', Asset::class);

        $data = $this->validateWith(CreateAssetRequest::class, $this->assetFormInput($request));

        if ($request->boolean('dry_run')) {
            return $this->dryRunResponse("create asset {$data['asset_code']}", $data);
        }

        [$base, $detail] = Asset::splitValidated($data);
        $asset           = Asset::createWithDetail($base, $detail)->load(['store:id,store_code', 'playerDetail']);

        return Response::json(['created' => self::assetSummary($asset)]);
    }

    public function schema(JsonSchema $schema): array
    {
        $fields = $this->assetFieldSchema($schema);

        foreach (['asset_name', 'manufacturer', 'model'] as $required) {
            $fields[$required]->required();
        }

        return [
            'asset_code'   => $schema->string()->description('Unique code, e.g. "PAN-SCR-0001".')->required(),
            'asset_type'   => $schema->string()->enum(self::assetTypeValues())->required(),
            'store_code'   => $schema->string()->description('The store it is deployed at, e.g. "PAN-MIR-001".')->required(),
            'asset_status' => $schema->string()->enum(self::assetStatusValues())->required(),
        ] + $fields;
    }
}
