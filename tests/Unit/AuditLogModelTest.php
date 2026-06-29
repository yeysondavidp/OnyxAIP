<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Proves AuditLog is append-only at the application level (US-00.5).
 * Pure unit test — no DB required.
 */
class AuditLogModelTest extends TestCase
{
    public function test_update_throws_logic_exception(): void
    {
        $this->expectException(LogicException::class);
        (new AuditLog)->update(['action' => 'tampered']);
    }

    public function test_delete_throws_logic_exception(): void
    {
        $this->expectException(LogicException::class);
        (new AuditLog)->delete();
    }

    public function test_force_delete_throws_logic_exception(): void
    {
        $this->expectException(LogicException::class);
        (new AuditLog)->forceDelete();
    }

    public function test_has_no_updated_at_column(): void
    {
        $this->assertNull(AuditLog::UPDATED_AT);
    }
}
