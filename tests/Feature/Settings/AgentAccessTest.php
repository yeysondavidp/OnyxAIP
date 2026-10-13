<?php

use App\Enums\AgentAbility;
use App\Enums\UserRole;
use App\Jobs\WriteAuditLog;
use App\Models\Client;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\AgentAccessService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function agentPayload(array $overrides = []): array
{
    return array_merge([
        'name'            => 'Claude (agent)',
        'client_id'       => null,
        'abilities'       => [AgentAbility::RegisterRead->value, AgentAbility::AssetsWrite->value],
        'expires_in_days' => 30,
    ], $overrides);
}

function issueAgent(array $abilities = [AgentAbility::RegisterRead]): array
{
    return app(AgentAccessService::class)->createAgent(User::factory()->pm()->create(), 'Claude (agent)', null, $abilities, 30);
}

it('pm can create an agent and sees its key once', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->post(route('agents.store'), agentPayload())
        ->assertRedirect(route('agents.index'))
        ->assertSessionHas('new_agent_token');

    $agent = User::where('role', UserRole::Agent)->sole();
    $token = $agent->tokens()->sole();

    expect([
        'abilities'  => $token->abilities,
        'created_by' => $token->created_by_user_id,
        'expires'    => $token->expires_at->isSameDay(now()->addDays(30)),
        'email'      => str_ends_with($agent->email, '@agents.invalid'),
        'hashed'     => ! str_contains(session('new_agent_token')['token'], $token->token),
        'prefix'     => str_contains(session('new_agent_token')['token'], '|onyx_'),
    ])->toBe([
        'abilities'  => ['register:read', 'assets:write'],
        'created_by' => $pm->id,
        'expires'    => true,
        'email'      => true,
        'hashed'     => true,
        'prefix'     => true,
    ]);
});

it('the new key is shown on the next page only', function () {
    $pm = User::factory()->pm()->create();
    $this->actingAs($pm)->post(route('agents.store'), agentPayload());
    $plain = session('new_agent_token')['token'];

    $this->get(route('agents.index'))->assertSee($plain);
    $this->get(route('agents.index'))->assertDontSee($plain);
});

it('a client-scoped agent only sees that client', function () {
    $client = Client::factory()->create();

    $this->actingAs(User::factory()->pm()->create())
        ->post(route('agents.store'), agentPayload(['client_id' => $client->id]));

    expect(User::where('role', UserRole::Agent)->sole()->permittedClientIds())->toBe([$client->id]);
});

it('rejects abilities outside the allow-list', function () {
    $this->actingAs(User::factory()->pm()->create())
        ->post(route('agents.store'), agentPayload(['abilities' => ['*']]))
        ->assertSessionHasErrors('abilities.0');
});

it('rejects an expiry longer than 90 days', function () {
    $this->actingAs(User::factory()->pm()->create())
        ->post(route('agents.store'), agentPayload(['expires_in_days' => 365]))
        ->assertSessionHasErrors('expires_in_days');
});

it('requires at least one ability', function () {
    $this->actingAs(User::factory()->pm()->create())
        ->post(route('agents.store'), agentPayload(['abilities' => []]))
        ->assertSessionHasErrors('abilities');
});

it('non-pm roles cannot manage agents', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->post(route('agents.store'), agentPayload())
        ->assertForbidden();
})->with(['technician', 'clientUser', 'agent']);

it('guests are sent to login', function () {
    $this->get(route('agents.index'))->assertRedirect(route('login'));
});

it('pm can revoke a key and the revocation is audited', function () {
    [$agent] = issueAgent();
    $token   = $agent->tokens()->sole();
    Bus::fake([WriteAuditLog::class]);

    $this->actingAs(User::factory()->pm()->create())
        ->delete(route('agents.tokens.destroy', [$agent, $token]))
        ->assertRedirect(route('agents.index'));

    expect(PersonalAccessToken::find($token->id))->toBeNull();
    Bus::assertDispatched(WriteAuditLog::class, fn (WriteAuditLog $job) => $job->action === 'agent_token.revoked');
});

it('a key cannot be revoked through another agent', function () {
    [$agentA] = issueAgent();
    [$agentB] = issueAgent();

    $this->actingAs(User::factory()->pm()->create())
        ->delete(route('agents.tokens.destroy', [$agentB, $agentA->tokens()->sole()]))
        ->assertNotFound();
});

it('keys cannot be issued to a non-agent user', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->post(route('agents.tokens.store', $pm), agentPayload())
        ->assertNotFound();
});

it('pm can issue an extra key to an existing agent', function () {
    [$agent] = issueAgent();

    $this->actingAs(User::factory()->pm()->create())
        ->post(route('agents.tokens.store', $agent), agentPayload())
        ->assertSessionHas('new_agent_token');

    expect($agent->tokens()->count())->toBe(2);
});

it('an agent cannot sign in to the web app even with the right password', function () {
    $agent = User::factory()->agent()->create(['password' => 'correct-horse-battery']);

    $this->post(route('login'), ['email' => $agent->email, 'password' => 'correct-horse-battery'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('an agent is refused on pm pages', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->get(route('dashboard'))
        ->assertForbidden();
});

it('the database refuses a token without an expiry', function () {
    $agent = User::factory()->agent()->create();

    DB::table('personal_access_tokens')->insert([
        'tokenable_type' => $agent->getMorphClass(),
        'tokenable_id'   => $agent->id,
        'name'           => 'no expiry',
        'token'          => hash('sha256', 'x'),
        'expires_at'     => null,
    ]);
})->throws(QueryException::class);

it('the database refuses an unknown role', function () {
    DB::table('users')->insert([
        'name'     => 'Root',
        'email'    => 'root@example.test',
        'password' => 'x',
        'role'     => 'root',
    ]);
})->throws(QueryException::class);

it('pm can open the create-agent and new-key forms', function () {
    [$agent] = issueAgent();
    $this->actingAs(User::factory()->pm()->create());

    $this->get(route('agents.create'))->assertOk()->assertSee('Read the register');
    $this->get(route('agents.tokens.create', $agent))->assertOk()->assertSee('New key for Claude (agent)');
});

it('the agent list shows each key and who issued it', function () {
    issueAgent();

    $this->actingAs(User::factory()->pm()->create())
        ->get(route('agents.index'))
        ->assertOk()
        ->assertSee('Claude (agent)')
        ->assertSee('Read the register');
});
