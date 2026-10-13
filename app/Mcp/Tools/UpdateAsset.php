<?php

namespace App\Mcp\Tools;

use App\Enums\AgentAbility;
use App\Http\Requests\UpdateAssetRequest;
use App\Mcp\Tools\Concerns\AcceptsAssetFields;
use App\Models\Asset;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update_asset')]
#[Description('Change details of an existing asset (name, model, serial, location notes, type-specific fields, or move it to another store). Send only the fields to change. Status changes go through change_asset_status; the asset type and code cannot be changed here. Run with dry_run: true first.')]
class UpdateAsset extends AgentTool
{
    use AcceptsAssetFields;

    /** Detail-row columns that are not form fields (or are secrets agents never send). */
    private const NON_FORM_DETAIL_COLUMNS = ['id', 'asset_id', 'created_at', 'updated_at', 'wifi_password', 'admin_password'];

    protected function ability(): AgentAbility
    {
        return AgentAbility::AssetsWrite;
    }

    public function handle(Request $request): Response
    {
        $agent = $this->agent($request);

        $code  = (string) $request->get('asset_code');
        $asset = Asset::where('asset_code', $code)->first();

        if ($asset === null) {
            return Response::error("No asset with code {$code} is visible to this access key.");
        }

        $this->authorizeFor($agent, 'update', $asset);

        $changes = Arr::except($this->assetFormInput($request), ['asset_code']);

        foreach (['asset_type' => 'This tool cannot change an asset\'s type.', 'asset_status' => 'Use change_asset_status to change an asset\'s status.'] as $field => $message) {
            if (array_key_exists($field, $changes) && $changes[$field] !== $asset->getRawOriginal($field)) {
                throw ValidationException::withMessages([$field => $message]);
            }
        }

        Asset::loadTypeDetails($asset->newCollection([$asset]));
        $detail = collect($asset->getRelations())->first();

        $current = array_merge(
            Arr::only($asset->getAttributes(), Asset::BASE_FIELDS),
            $detail === null ? [] : Arr::except($detail->getAttributes(), self::NON_FORM_DETAIL_COLUMNS),
        );

        $data = $this->validateWith(UpdateAssetRequest::class, array_merge($current, $changes), ['asset' => $asset]);

        $changed = array_keys(array_diff_assoc(array_map('strval', array_intersect_key($data, $changes)), array_map('strval', array_intersect_key($current, $changes))));

        if ($request->boolean('dry_run')) {
            return $this->dryRunResponse("update asset {$asset->asset_code}", Arr::only($data, $changed));
        }

        [$base, $detailData] = Asset::splitValidated($data);
        $asset->updateWithDetail($base, $detailData);

        return Response::json([
            'updated' => self::assetSummary($asset->fresh(['store:id,store_code', 'playerDetail']) ?? $asset),
            'changed' => $changed,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'asset_code' => $schema->string()->description('The asset to change, e.g. "PAN-PLY-0001".')->required(),
            'store_code' => $schema->string()->description('Move the asset to this store.'),
        ] + $this->assetFieldSchema($schema);
    }
}
