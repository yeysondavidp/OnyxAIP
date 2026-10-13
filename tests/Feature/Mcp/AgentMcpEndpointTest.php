<?php

use App\Enums\AgentAbility;
use App\Jobs\WriteAuditLog;
use App\Mcp\Tools\GetStore;
use App\Models\Asset;
use App\Models\AssetInfrastructureDetail;
use App\Models\ServiceJob;
use App\Models\Store;
use App\Models\User;
use App\Services\AgentAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\TestResponse;
use Laravel\Mcp\Request;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/** @param list<AgentAbility> $abilities */
function agentKey(array $abilities = [AgentAbility::RegisterRead], ?int $clientId = null): string
{
    [, $token] = app(AgentAccessService::class)
        ->createAgent(User::factory()->pm()->create(), 'Claude (agent)', $clientId, $abilities, 30);

    return $token;
}

function mcp(?string $key, string $method, array $params = []): TestResponse
{
    $headers = ['Accept' => 'application/json, text/event-stream'];

    if ($key !== null) {
        $headers['Authorization'] = 'Bearer '.$key;
    }

    return test()->withHeaders($headers)->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id'      => 1,
        'method'  => $method,
        'params'  => $params,
    ]);
}

function callTool(string $key, string $tool, array $arguments = []): array
{
    $result = mcp($key, 'tools/call', ['name' => $tool, 'arguments' => $arguments])->assertOk()->json('result');

    return [
        'isError' => $result['isError'] ?? false,
        'data'    => json_decode($result['content'][0]['text'] ?? 'null', true),
        'text'    => $result['content'][0]['text'] ?? '',
    ];
}

it('refuses calls without an access key', function () {
    mcp(null, 'tools/list')->assertUnauthorized();
});

it('does not accept a logged-in pm browser session', function () {
    $this->actingAs(User::factory()->pm()->create());

    mcp(null, 'tools/list')->assertUnauthorized();
});

it('refuses an expired key', function () {
    $key = agentKey();
    $this->travel(31)->days();

    mcp($key, 'tools/list')->assertUnauthorized();
});

it('refuses a revoked key', function () {
    $key = agentKey();
    User::where('role', 'agent')->sole()->tokens()->delete();

    mcp($key, 'tools/list')->assertUnauthorized();
});

it('refuses a token that belongs to a pm', function () {
    $pmToken = User::factory()->pm()->create()->createToken('pm', ['*'], now()->addDay())->plainTextToken;

    mcp($pmToken, 'tools/list')->assertForbidden();
});

it('lists the read tools for a key with register:read', function () {
    $names = collect(mcp(agentKey(), 'tools/list')->assertOk()->json('result.tools'))->pluck('name')->sort()->values()->all();

    expect($names)->toBe(['get_job', 'get_store', 'search_assets', 'search_stores']);
});

it('hides and refuses read tools for a key without register:read', function () {
    $key = agentKey([AgentAbility::AssetsWrite]);

    expect(mcp($key, 'tools/list')->json('result.tools'))->toBe([]);
    mcp($key, 'tools/call', ['name' => 'get_store', 'arguments' => ['store_code' => 'X']])
        ->assertJsonPath('error.message', 'Tool [get_store] not found.');
});

it('get_store returns the store, its assets and display groups without secrets', function () {
    $store  = Store::factory()->create(['store_code' => 'PAN-MIR-001']);
    $router = Asset::factory()->create(['client_id' => $store->client_id, 'store_id' => $store->id, 'asset_type' => 'infrastructure', 'asset_code' => 'PAN-INF-0009']);
    AssetInfrastructureDetail::create(['asset_id' => $router->id, 'wifi_password' => 'super-secret-wifi', 'admin_password' => 'super-secret-admin']);

    $result = callTool(agentKey(), 'get_store', ['store_code' => 'PAN-MIR-001']);

    expect([
        'store'  => $result['data']['store']['store_code'],
        'assets' => array_column($result['data']['assets'], 'asset_code'),
        'leaks'  => str_contains($result['text'], 'super-secret'),
    ])->toBe([
        'store'  => 'PAN-MIR-001',
        'assets' => ['PAN-INF-0009'],
        'leaks'  => false,
    ]);
});

it('a client-scoped key cannot see another client', function () {
    $mine  = Store::factory()->create(['store_code' => 'MINE-001']);
    $other = Store::factory()->create(['store_code' => 'OTHER-001']);

    $key = agentKey(clientId: $mine->client_id);

    expect([
        'search' => array_column(callTool($key, 'search_stores')['data']['stores'], 'store_code'),
        'get'    => callTool($key, 'get_store', ['store_code' => $other->store_code])['isError'],
    ])->toBe(['search' => ['MINE-001'], 'get' => true]);
});

it('search_assets finds an asset by serial number', function () {
    Asset::factory()->create(['asset_code' => 'PAN-PLY-0001', 'serial_number' => 'MIB192600317']);
    Asset::factory()->create(['asset_code' => 'PAN-PLY-0002', 'serial_number' => 'OTHER']);

    expect(array_column(callTool(agentKey(), 'search_assets', ['query' => '2600317'])['data']['assets'], 'asset_code'))
        ->toBe(['PAN-PLY-0001']);
});

it('get_job returns the job without technician links or file paths', function () {
    $job = ServiceJob::factory()->create(['job_reference' => 'JOB-2026-904']);

    $result = callTool(agentKey(), 'get_job', ['job_reference' => 'JOB-2026-904']);

    expect([
        'reference' => $result['data']['job']['job_reference'],
        'leaks'     => str_contains($result['text'], 'invitation_token') || str_contains($result['text'], 'stored_path'),
    ])->toBe(['reference' => $job->job_reference, 'leaks' => false]);
});

it('rejects invalid tool input in plain language', function () {
    expect(callTool(agentKey(), 'get_store', [])['text'])->toContain('store code');
});

it('audit-logs every tool call against the agent', function () {
    $key = agentKey();
    Bus::fake([WriteAuditLog::class]);

    callTool($key, 'search_stores');

    Bus::assertDispatched(WriteAuditLog::class, fn (WriteAuditLog $job) => $job->action === 'mcp.tool_called'
        && $job->userRole                                                               === 'agent'
        && $job->after['tool']                                                          === 'search_stores');
});

it('rate-limits each key to 60 calls a minute', function () {
    $key = agentKey();

    foreach (range(1, 60) as $_) {
        mcp($key, 'ping');
    }

    mcp($key, 'ping')->assertStatus(429);
});

it('a tool re-checks its ability even when called directly', function () {
    Sanctum::actingAs(User::factory()->agent()->create(), [AgentAbility::AssetsWrite->value]);

    (new GetStore)->handle(new Request(['store_code' => 'X']));
})->throws(AuthorizationException::class, 'not allowed to: Read the register');

it('answers the MCP initialize handshake', function () {
    mcp(agentKey(), 'initialize', [
        'protocolVersion' => '2025-06-18',
        'capabilities'    => (object) [],
        'clientInfo'      => ['name' => 'test', 'version' => '1.0'],
    ])->assertOk()->assertJsonPath('result.serverInfo.name', 'ONYX AIP');
});
