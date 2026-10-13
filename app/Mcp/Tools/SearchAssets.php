<?php

namespace App\Mcp\Tools;

use App\Enums\AgentAbility;
use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('search_assets')]
#[Description('Find assets by asset code, serial number, name or MAC address, optionally filtered by store code, type or status. Use it to check whether a device is already registered before adding it. Returns at most 50 assets.')]
class SearchAssets extends AgentTool
{
    protected function ability(): AgentAbility
    {
        return AgentAbility::RegisterRead;
    }

    public function handle(Request $request): Response
    {
        $agent = $this->agent($request);
        $this->authorizeFor($agent, 'viewAny', Asset::class);

        $input = $request->validate([
            'query'        => ['nullable', 'string', 'max:100'],
            'store_code'   => ['nullable', 'string', 'max:40'],
            'asset_type'   => ['nullable', Rule::enum(AssetType::class)],
            'asset_status' => ['nullable', Rule::enum(AssetStatus::class)],
        ]);

        $query = $input['query'] ?? null;
        $like  = $query === null ? null : '%'.addcslashes($query, '%_\\').'%';

        $assets = Asset::query()
            ->with(['store:id,store_code', 'playerDetail'])
            ->when($like !== null, fn ($q) => $q->where(fn ($w) => $w
                ->where('asset_code', 'like', $like)
                ->orWhere('serial_number', 'like', $like)
                ->orWhere('asset_name', 'like', $like)
                ->orWhereHas('playerDetail', fn ($p) => $p->where('mac_address', 'like', $like))))
            ->when($input['store_code'] ?? null, fn ($q, $code) => $q->whereHas('store', fn ($s) => $s->where('store_code', $code)))
            ->when($input['asset_type'] ?? null, fn ($q, $type) => $q->where('asset_type', $type))
            ->when($input['asset_status'] ?? null, fn ($q, $status) => $q->where('asset_status', $status))
            ->orderBy('asset_code')
            ->limit(self::MAX_RESULTS + 1)
            ->get();

        return Response::json([
            'assets'    => $assets->take(self::MAX_RESULTS)->map(fn (Asset $a) => self::assetSummary($a))->values(),
            'truncated' => $assets->count() > self::MAX_RESULTS,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query'        => $schema->string()->description('Part of the asset code, serial number, name or MAC address.'),
            'store_code'   => $schema->string()->description('Limit to one store, e.g. "PAN-MIR-001".'),
            'asset_type'   => $schema->string()->enum(array_column(AssetType::cases(), 'value')),
            'asset_status' => $schema->string()->enum(array_column(AssetStatus::cases(), 'value')),
        ];
    }
}
