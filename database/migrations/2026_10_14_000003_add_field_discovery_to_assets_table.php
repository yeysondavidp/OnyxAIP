<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EPIC-21: devices a technician registers on site (status pending_verification, D7) record
 * where they were found and by whom, what the technician typed that has no column yet, the
 * photo, and which PM verified them. The CHECK makes the DB enforce App\Enums\AssetStatus
 * (fixed list on purpose; a new status needs its own migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('found_on_job_id')->nullable()->after('notes')->constrained('service_jobs')->nullOnDelete();
            $table->foreignId('found_by_technician_profile_id')->nullable()->after('found_on_job_id')->constrained('technician_profiles')->nullOnDelete();
            $table->json('discovery_data')->nullable()->after('found_by_technician_profile_id');
            $table->string('discovery_photo_path')->nullable()->after('discovery_data');
            $table->timestamp('verified_at')->nullable()->after('discovery_photo_path');
            $table->foreignId('verified_by_user_id')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
        });

        DB::statement("ALTER TABLE assets ADD CONSTRAINT assets_status_check CHECK (asset_status IN ('active', 'faulty', 'offline', 'under_maintenance', 'decommissioned', 'pending_verification'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE assets DROP CHECK assets_status_check');

        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by_user_id');
            $table->dropColumn(['verified_at', 'discovery_photo_path', 'discovery_data']);
            $table->dropConstrainedForeignId('found_by_technician_profile_id');
            $table->dropConstrainedForeignId('found_on_job_id');
        });
    }
};
