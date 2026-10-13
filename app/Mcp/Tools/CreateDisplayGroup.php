<?php

namespace App\Mcp\Tools;

use App\Enums\AgentAbility;
use App\Http\Requests\CreateDisplayGroupRequest;
use App\Models\Asset;
use App\Models\DisplayGroup;
use App\Models\Store;
use App\Services\DisplayGroupService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_display_group')]
#[Description('Create a display group at a store: one media player driving one or more screens. The player and each screen must be at that store and not already in another group. Run with dry_run: true first.')]
class CreateDisplayGroup extends AgentTool
{
    protected function ability(): AgentAbility
    {
        return AgentAbility::JobsWrite;
    }

    public function handle(Request $request, DisplayGroupService $groups): Response
    {
        $agent = $this->agent($request);

        $input = $request->validate([
            'store_code'   => ['required', 'string', 'max:40'],
            'player_code'  => ['required', 'string', 'max:40'],
            'screen_codes' => ['required', 'array', 'min:1', 'max:'.self::MAX_RESULTS],
        ]);

        $store = Store::where('store_code', $input['store_code'])->first();

        if ($store === null) {
            return Response::error("No store with code {$input['store_code']} is visible to this access key.");
        }

        $this->authorizeFor($agent, 'create', [DisplayGroup::class, $store]);

        $ids = Asset::where('store_id', $store->id)
            ->whereIn('asset_code', [$input['player_code'], ...$input['screen_codes']])
            ->pluck('id', 'asset_code');

        $data = $this->validateWith(CreateDisplayGroupRequest::class, [
            'group_name'         => $request->get('group_name'),
            'player_asset_id'    => $ids[$input['player_code']] ?? null,
            'screen_asset_ids'   => array_map(fn (string $code) => $ids[$code] ?? null, $input['screen_codes']),
            'layout_description' => $request->get('layout_description'),
            'notes'              => $request->get('notes'),
        ], ['store' => $store]);

        if ($request->boolean('dry_run')) {
            return $this->dryRunResponse("create display group \"{$data['group_name']}\" at {$store->store_code}", [
                'player'  => $input['player_code'],
                'screens' => $input['screen_codes'],
            ]);
        }

        $group = $groups->create($store, $data);

        return Response::json(['created' => [
            'group_name' => $group->group_name,
            'store_code' => $store->store_code,
            'player'     => $input['player_code'],
            'screens'    => $input['screen_codes'],
        ]]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'store_code'         => $schema->string()->required(),
            'group_name'         => $schema->string()->description('e.g. "Front window".')->required(),
            'player_code'        => $schema->string()->description('Asset code of the media player.')->required(),
            'screen_codes'       => $schema->array()->items($schema->string())->description('Asset codes of the screens it drives.')->required(),
            'layout_description' => $schema->string()->description('e.g. "1 portrait screen in a floor totem".'),
            'notes'              => $schema->string(),
            'dry_run'            => $schema->boolean()->description('true = validate without saving.'),
        ];
    }
}
