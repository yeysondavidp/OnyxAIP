<?php

namespace App\Mcp\Tools;

use App\Enums\AgentAbility;
use App\Models\Asset;
use App\Models\DisplayGroup;
use App\Models\Store;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get_store')]
#[Description('Get one store by store code (e.g. PAN-MIR-001): its details, every registered asset and its display groups (player to screens). Never returns passwords or secrets.')]
class GetStore extends AgentTool
{
    protected function ability(): AgentAbility
    {
        return AgentAbility::RegisterRead;
    }

    public function handle(Request $request): Response
    {
        $agent = $this->agent($request);

        $input = $request->validate(['store_code' => ['required', 'string', 'max:40']]);

        $store = Store::with('client:id,client_code,client_name')->where('store_code', $input['store_code'])->first();

        if ($store === null) {
            return Response::error("No store with code {$input['store_code']} is visible to this access key.");
        }

        $this->authorizeFor($agent, 'view', $store);

        $assets = Asset::with(['store:id,store_code', 'playerDetail'])
            ->where('store_id', $store->id)
            ->orderBy('asset_code')
            ->get();

        $groups = DisplayGroup::with(['player:id,asset_code', 'screens'])
            ->where('store_id', $store->id)
            ->orderBy('group_name')
            ->get();

        return Response::json([
            'store' => [
                'store_code'     => $store->store_code,
                'store_name'     => $store->store_name,
                'client'         => $store->client?->client_code,
                'store_type'     => self::enumValue($store->store_type),
                'address_line1'  => $store->address_line1,
                'suburb'         => $store->suburb,
                'state'          => self::enumValue($store->state),
                'postcode'       => $store->postcode,
                'country'        => self::enumValue($store->country),
                'store_timezone' => $store->store_timezone,
                'is_active'      => $store->is_active,
            ],
            'assets'         => $assets->map(fn (Asset $a) => self::assetSummary($a))->values(),
            'display_groups' => $groups->map(fn (DisplayGroup $g) => [
                'group_name'         => $g->group_name,
                'player'             => $g->player?->asset_code,
                'screens'            => $g->screens->pluck('asset_code')->values(),
                'layout_description' => $g->layout_description,
            ])->values(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'store_code' => $schema->string()->description('The store code, e.g. "PAN-MIR-001".')->required(),
        ];
    }
}
