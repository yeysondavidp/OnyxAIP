<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-client AI setting (ADR-003 §6, D8). Defaults to "off" so nothing is sent to an AI provider
 * until a PM opts the client in; the CHECK mirrors App\Enums\AiMode (fixed list on purpose).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('ai_mode', 20)->default('off')->after('is_active');
        });

        DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_ai_mode_check CHECK (ai_mode IN ('off', 'label_crops', 'public_photos'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE clients DROP CHECK clients_ai_mode_check');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('ai_mode');
        });
    }
};
