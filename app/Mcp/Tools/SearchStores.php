<?php

namespace App\Mcp\Tools;

use App\Enums\AgentAbility;
use App\Models\Store;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('search_stores')]
#[Description('Find stores by name, store code or suburb, optionally for one client (client code such as PAN, SEP, DIO). Returns at most 50 stores; active stores only unless include_inactive is true.')]
class SearchStores extends AgentTool
{
    protected function ability(): AgentAbility
    {
        return AgentAbility::RegisterRead;
    }

    public function handle(Request $request): Response
    {
        $agent = $this->agent($request);
        $this->authorizeFor($agent, 'viewAny', Store::class);

        $input = $request->validate([
            'query'            => ['nullable', 'string', 'max:100'],
            'client_code'      => ['nullable', 'string', 'max:20'],
            'include_inactive' => ['nullable', 'boolean'],
        ]);

        $query = $input['query'] ?? null;
        $like  = $query === null ? null : '%'.addcslashes($query, '%_\\').'%';

        $stores = Store::query()
            ->with('client:id,client_code,client_name')
            ->when($like !== null, fn ($q) => $q->where(fn ($w) => $w
                ->where('store_name', 'like', $like)
                ->orWhere('store_code', 'like', $like)
                ->orWhere('suburb', 'like', $like)))
            ->when($input['client_code'] ?? null, fn ($q, $code) => $q->whereHas('client', fn ($c) => $c->where('client_code', $code)))
            ->unless($input['include_inactive'] ?? false, fn ($q) => $q->where('is_active', true))
            ->orderBy('store_code')
            ->limit(self::MAX_RESULTS + 1)
            ->get();

        return Response::json([
            'stores' => $stores->take(self::MAX_RESULTS)->map(fn (Store $s) => [
                'store_code' => $s->store_code,
                'store_name' => $s->store_name,
                'client'     => $s->client?->client_code,
                'suburb'     => $s->suburb,
                'state'      => self::enumValue($s->state),
                'country'    => self::enumValue($s->country),
                'is_active'  => $s->is_active,
            ])->values(),
            'truncated' => $stores->count() > self::MAX_RESULTS,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query'            => $schema->string()->description('Part of the store name, store code or suburb, e.g. "Miranda".'),
            'client_code'      => $schema->string()->description('Limit to one client, e.g. "PAN".'),
            'include_inactive' => $schema->boolean()->description('Also return deactivated stores.'),
        ];
    }
}
