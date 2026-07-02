<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB-level idempotency for technician reminder / link-expiry-warning notifications
 * (US-13.4) — one row per (job, technician, notification type) ever fired.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_notification_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_id')->index();
            $table->unsignedBigInteger('technician_profile_id')->index();
            $table->string('notification_type', 50); // 'reminder' | 'link_expiry_warning'
            $table->timestamp('sent_at');

            $table->foreign('job_id', 'tnl_job_id_fk')->references('id')->on('service_jobs')->cascadeOnDelete();
            $table->foreign('technician_profile_id', 'tnl_technician_profile_id_fk')->references('id')->on('technician_profiles')->cascadeOnDelete();
            $table->unique(['job_id', 'technician_profile_id', 'notification_type'], 'tnl_job_tech_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_notification_log');
    }
};
