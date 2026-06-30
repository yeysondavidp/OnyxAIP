<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_reference')->unique();
            $table->string('job_name');
            $table->text('job_description');
            $table->string('job_type');         // JobType enum value
            $table->unsignedBigInteger('client_id')->index();
            $table->unsignedBigInteger('store_id')->index();
            $table->string('job_timezone');
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_time')->nullable();
            $table->string('early_start_window')->default('anytime'); // EarlyStartWindow enum
            $table->string('job_status')->default('draft')->index();  // JobStatus enum
            $table->unsignedBigInteger('parent_job_id')->nullable()->index();
            $table->unsignedTinyInteger('job_level')->default(0); // 0=root, 1=sub, 2=remediation
            $table->string('client_email')->nullable();
            $table->string('client_name')->nullable();
            $table->boolean('sla_breached')->default(false)->index();
            $table->text('force_complete_reason')->nullable();
            $table->timestamps();
            $table->softDeletes(); // Cancelled jobs are soft-deleted (US-08.3)

            $table->foreign('client_id', 'sj_client_id_fk')->references('id')->on('clients')->restrictOnDelete();
            $table->foreign('store_id', 'sj_store_id_fk')->references('id')->on('stores')->restrictOnDelete();
            // Self-referencing FK: on RESTRICT so parent cannot be deleted while children exist
            $table->foreign('parent_job_id', 'sj_parent_job_id_fk')->references('id')->on('service_jobs')->restrictOnDelete();

            // Perf indexes (SRA §14.4)
            $table->index('scheduled_date');
            $table->index(['client_id', 'job_status']);
        });

        // Pivot: job ↔ asset (US-08.2)
        Schema::create('job_assets', function (Blueprint $table) {
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('asset_id');
            $table->primary(['job_id', 'asset_id']); // composite PK = unique + NOT NULL
            $table->foreign('job_id', 'ja_job_id_fk')->references('id')->on('service_jobs')->cascadeOnDelete();
            $table->foreign('asset_id', 'ja_asset_id_fk')->references('id')->on('assets')->cascadeOnDelete();
        });

        // Pivot: job ↔ technician, each with independent lifecycle (US-08.4)
        Schema::create('job_technicians', function (Blueprint $table) {
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('user_id');
            $table->string('technician_status')->default('invited'); // TechnicianJobStatus enum
            $table->text('force_complete_reason')->nullable();
            $table->primary(['job_id', 'user_id']); // composite PK = unique + NOT NULL
            $table->foreign('job_id', 'jt_job_id_fk')->references('id')->on('service_jobs')->cascadeOnDelete();
            $table->foreign('user_id', 'jt_user_id_fk')->references('id')->on('users')->cascadeOnDelete();
        });

        // PM attachments (US-08.6)
        Schema::create('job_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_id')->index();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size')->nullable(); // bytes
            $table->timestamps();
            $table->foreign('job_id', 'jatt_job_id_fk')->references('id')->on('service_jobs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_attachments');
        Schema::dropIfExists('job_technicians');
        Schema::dropIfExists('job_assets');
        Schema::dropIfExists('service_jobs');
    }
};
