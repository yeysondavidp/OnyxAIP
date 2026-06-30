<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Technician directory (US-09.1, SRA §11)
        Schema::create('technician_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            // JSON arrays — nullable; model defaults to [] on read
            $table->json('specialty_categories')->nullable();
            $table->json('certifications')->nullable();
            $table->json('preferred_client_ids')->nullable();
            $table->text('asset_competency')->nullable();
            $table->boolean('is_active')->default(true)->index();
            // Optional link to a technician User account
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->foreign('user_id', 'tp_user_id_fk')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Migrate job_technicians: replace user_id with technician_profile_id.
        // FKs must be dropped before PK can be modified in MySQL.
        DB::statement('ALTER TABLE job_technicians DROP FOREIGN KEY jt_job_id_fk');
        DB::statement('ALTER TABLE job_technicians DROP FOREIGN KEY jt_user_id_fk');
        DB::statement('ALTER TABLE job_technicians DROP PRIMARY KEY');
        DB::statement('ALTER TABLE job_technicians DROP COLUMN user_id');
        DB::statement('ALTER TABLE job_technicians ADD COLUMN technician_profile_id BIGINT UNSIGNED NOT NULL AFTER job_id');
        DB::statement('ALTER TABLE job_technicians ADD INDEX (job_id)');
        DB::statement('ALTER TABLE job_technicians ADD PRIMARY KEY (job_id, technician_profile_id)');
        DB::statement('ALTER TABLE job_technicians ADD CONSTRAINT jt_job_id_fk FOREIGN KEY (job_id) REFERENCES service_jobs(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE job_technicians ADD CONSTRAINT jt_profile_id_fk FOREIGN KEY (technician_profile_id) REFERENCES technician_profiles(id) ON DELETE CASCADE');

        // Invitation token for link invalidation (US-09.4)
        Schema::table('job_technicians', function (Blueprint $table) {
            $table->string('invitation_token', 64)->nullable()->unique();
            $table->timestamp('token_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('job_technicians', function (Blueprint $table) {
            $table->dropColumn(['invitation_token', 'token_expires_at']);
        });

        DB::statement('ALTER TABLE job_technicians DROP PRIMARY KEY');
        DB::statement('ALTER TABLE job_technicians DROP FOREIGN KEY jt_profile_id_fk');
        DB::statement('ALTER TABLE job_technicians DROP COLUMN technician_profile_id');
        DB::statement('ALTER TABLE job_technicians ADD COLUMN user_id BIGINT UNSIGNED NOT NULL AFTER job_id');
        DB::statement('ALTER TABLE job_technicians ADD PRIMARY KEY (job_id, user_id)');
        DB::statement('ALTER TABLE job_technicians ADD CONSTRAINT jt_user_id_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');

        Schema::dropIfExists('technician_profiles');
    }
};
