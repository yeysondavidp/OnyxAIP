<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Proves that ClientScope blocks cross-tenant reads.
 * US-00.4 acceptance criteria: "rows of other clients are never returned —
 * proven by a failing-without-scope test."
 *
 * Updated in US-01.2: scope restriction is now tied to the client_user role.
 * PMs are always unrestricted regardless of client_id. client_users are
 * restricted to their own client_id.
 */
class ClientScopeTest extends TestCase
{
    use RefreshDatabase;

    private Client $clientA;

    private Client $clientB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientA = Client::create(['name' => 'Client A', 'client_code' => 'CA']);
        $this->clientB = Client::create(['name' => 'Client B', 'client_code' => 'CB']);

        Store::create(['client_id' => $this->clientA->id, 'name' => 'Store A1', 'store_code' => 'CA-001']);
        Store::create(['client_id' => $this->clientA->id, 'name' => 'Store A2', 'store_code' => 'CA-002']);
        Store::create(['client_id' => $this->clientB->id, 'name' => 'Store B1', 'store_code' => 'CB-001']);
    }

    public function test_pm_with_no_client_id_sees_all_stores(): void
    {
        $pm = User::factory()->create(['client_id' => null]);
        $this->actingAs($pm);

        $this->assertSame(3, Store::count());
    }

    public function test_user_scoped_to_client_a_cannot_see_client_b_stores(): void
    {
        // client_user role + client_id → restricted to that client
        $user = User::factory()->clientUser()->create(['client_id' => $this->clientA->id]);
        $this->actingAs($user);

        $stores = Store::all();

        $this->assertSame(2, $stores->count());
        $stores->each(fn ($s) => $this->assertSame($this->clientA->id, $s->client_id));
    }

    public function test_user_scoped_to_client_b_cannot_see_client_a_stores(): void
    {
        $user = User::factory()->clientUser()->create(['client_id' => $this->clientB->id]);
        $this->actingAs($user);

        $stores = Store::all();

        $this->assertSame(1, $stores->count());
        $this->assertSame($this->clientB->id, $stores->first()->client_id);
    }

    public function test_without_scope_all_stores_are_visible(): void
    {
        $user = User::factory()->clientUser()->create(['client_id' => $this->clientA->id]);
        $this->actingAs($user);

        $this->assertSame(3, Store::allClients()->count());
    }

    public function test_client_id_set_from_context_on_create(): void
    {
        $user = User::factory()->clientUser()->create(['client_id' => $this->clientA->id]);
        $this->actingAs($user);

        // Don't pass client_id — the trait must inject it from the auth context.
        $store = Store::create(['name' => 'Auto-scoped Store', 'store_code' => 'CA-AUTO']);

        $this->assertSame($this->clientA->id, $store->client_id);
    }

    public function test_db_enforces_client_id_not_null(): void
    {
        $this->expectException(QueryException::class);

        DB::table('stores')->insert([
            'name'       => 'No client',
            'store_code' => 'NO-001',
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
