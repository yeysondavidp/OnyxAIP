<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // GPS timestamps + completion notes per (job, technician) (US-10.2, US-10.4)
        Schema::create('job_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_id')->index();
            $table->unsignedBigInteger('technician_profile_id')->index();
            $table->timestamp('start_timestamp_utc')->nullable();
            $table->decimal('start_lat', 10, 7)->nullable();
            $table->decimal('start_lng', 10, 7)->nullable();
            $table->string('start_gps_status', 20)->default('pending'); // pending|granted|denied|failed
            $table->timestamp('end_timestamp_utc')->nullable();
            $table->decimal('end_lat', 10, 7)->nullable();
            $table->decimal('end_lng', 10, 7)->nullable();
            $table->string('end_gps_status', 20)->nullable();
            $table->text('completion_notes')->nullable();
            $table->timestamps();

            $table->unique(['job_id', 'technician_profile_id']);
            $table->foreign('job_id', 'jcp_job_id_fk')->references('id')->on('service_jobs')->cascadeOnDelete();
            $table->foreign('technician_profile_id', 'jcp_profile_id_fk')->references('id')->on('technician_profiles')->cascadeOnDelete();
        });

        // Before/after photos per (job, technician) (US-10.2, US-10.4, US-10.6)
        Schema::create('job_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_id')->index();
            $table->unsignedBigInteger('technician_profile_id')->index();
            $table->string('type', 10);             // before|after (PhotoType enum)
            $table->string('stored_path');
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size')->nullable();
            // Idempotency key generated client-side (UUID) — prevents duplicate storage on retry
            $table->string('client_upload_id', 64);
            $table->timestamps();

            // Composite unique ensures idempotent re-upload (US-10.6 Engineering Bar)
            $table->unique(['job_id', 'technician_profile_id', 'client_upload_id'], 'jp_idempotency_unique');
            $table->foreign('job_id', 'jp_job_id_fk')->references('id')->on('service_jobs')->cascadeOnDelete();
            $table->foreign('technician_profile_id', 'jp_profile_id_fk')->references('id')->on('technician_profiles')->cascadeOnDelete();
        });

        // Per-asset outcomes from Screen 4 (US-10.4, EPIC-11)
        Schema::create('job_asset_outcomes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_id')->index();
            $table->unsignedBigInteger('asset_id')->index();
            $table->string('post_service_status', 30); // PostServiceStatus enum
            $table->string('technician_notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['job_id', 'asset_id']); // last outcome per asset per job wins
            $table->foreign('job_id', 'jao_job_id_fk')->references('id')->on('service_jobs')->cascadeOnDelete();
            $table->foreign('asset_id', 'jao_asset_id_fk')->references('id')->on('assets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_asset_outcomes');
        Schema::dropIfExists('job_photos');
        Schema::dropIfExists('job_checkpoints');
    }
};
