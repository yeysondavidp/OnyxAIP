<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the Auditable trait dispatches audit entries (US-00.5).
 *
 * QUEUE_CONNECTION=sync in phpunit.xml so jobs run immediately —
 * we can assert the DB row exists right after the model event fires.
 */
class AuditableTraitTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create(['client_name' => 'ACME', 'client_code' => 'ACM']);
    }

    public function test_created_event_writes_audit_log(): void
    {
        $pm = User::factory()->create(['client_id' => null]);
        $this->actingAs($pm);

        $store = Store::factory()->create([
            'client_id'  => $this->client->id,
            'store_name' => 'Test Store',
            'store_code' => 'ACM-001',
        ]);

        $log = AuditLog::where('auditable_type', Store::class)
            ->where('auditable_id', $store->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($log, 'A created audit entry must exist.');
        $this->assertSame($pm->id, $log->user_id);
        $this->assertNull($log->before);
        $this->assertArrayHasKey('store_name', $log->after);
    }

    public function test_updated_event_writes_audit_log(): void
    {
        $pm    = User::factory()->create(['client_id' => null]);
        $store = Store::factory()->create([
            'client_id'  => $this->client->id,
            'store_name' => 'Before',
            'store_code' => 'ACM-002',
        ]);
        $this->actingAs($pm);

        $store->update(['store_name' => 'After']);

        $log = AuditLog::where('auditable_type', Store::class)
            ->where('auditable_id', $store->id)
            ->where('action', 'updated')
            ->first();

        $this->assertNotNull($log, 'An updated audit entry must exist.');
        $this->assertSame('Before', $log->before['store_name'] ?? null);
        $this->assertSame('After', $log->after['store_name'] ?? null);
    }

    public function test_deleted_event_writes_audit_log(): void
    {
        $pm    = User::factory()->create(['client_id' => null]);
        $store = Store::factory()->create([
            'client_id'  => $this->client->id,
            'store_name' => 'To Delete',
            'store_code' => 'ACM-003',
        ]);
        $this->actingAs($pm);

        $storeId = $store->id;
        $store->delete();

        $log = AuditLog::where('auditable_type', Store::class)
            ->where('auditable_id', $storeId)
            ->where('action', 'deleted')
            ->first();

        $this->assertNotNull($log, 'A deleted audit entry must exist.');
        $this->assertNull($log->after);
        $this->assertArrayHasKey('store_name', $log->before);
    }

    public function test_sensitive_fields_are_stripped_from_diff(): void
    {
        User::factory()->create(['client_id' => null]);

        $log = AuditLog::where('auditable_type', User::class)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('password', (array) $log->after);
        $this->assertArrayNotHasKey('remember_token', (array) $log->after);
    }

    public function test_unauthenticated_action_records_null_user_id(): void
    {
        // No actingAs — simulates a CLI/seeder action
        Client::factory()->create(['client_name' => 'Anon Client', 'client_code' => 'ANN']);

        $log = AuditLog::where('auditable_type', Client::class)
            ->where('action', 'created')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertNull($log->user_id);
    }

    public function test_audit_log_cannot_be_updated(): void
    {
        $this->expectException(\LogicException::class);

        $log = AuditLog::where('action', 'created')->first()
            ?? AuditLog::create([
                'action'         => 'created',
                'auditable_type' => Client::class,
                'auditable_id'   => 1,
            ]);

        $log->update(['action' => 'tampered']);
    }
}
