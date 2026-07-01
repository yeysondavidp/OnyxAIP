<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Capture the asset's status at the moment it was attached to a job — the
        // "status before" reference point service_history needs at validation time,
        // taken before the §5.2 auto-transition to Under Maintenance (US-11.1, SRA §7).
        Schema::table('job_assets', function (Blueprint $table) {
            $table->string('status_before', 25)->nullable()->after('asset_id');
        });

        // Append-only per-asset service record written on job validation (US-11.1/11.3, SRA §7).
        // Never updated or deleted at the application level — same pattern as asset_history
        // and audit_logs (US-00.5).
        Schema::create('service_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('service_job_id');
            $table->date('service_date');
            $table->json('technician_profile_ids'); // list<int> — technicians who worked the job
            $table->string('job_type', 40);
            $table->string('status_before', 25);
            $table->string('status_after', 25);
            $table->string('technician_notes', 500)->nullable();
            $table->json('before_photo_paths')->nullable();
            $table->json('after_photo_paths')->nullable();
            $table->timestamp('created_at');

            $table->index('asset_id');
            $table->index('service_job_id');
            $table->index(['asset_id', 'service_date']);

            $table->foreign('asset_id', 'sh_asset_id_fk')->references('id')->on('assets')->cascadeOnDelete();
            $table->foreign('service_job_id', 'sh_job_id_fk')->references('id')->on('service_jobs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_history');

        Schema::table('job_assets', function (Blueprint $table) {
            $table->dropColumn('status_before');
        });
    }
};
