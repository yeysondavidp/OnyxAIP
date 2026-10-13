<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The DB guarantees what App\Enums\UserRole guarantees (US-20.1): a role outside
 * the enum is rejected at insert time, not just by the cast. The list is fixed
 * here on purpose; a new role needs its own migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('pm', 'technician', 'client_user', 'agent'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CHECK users_role_check');
    }
};
