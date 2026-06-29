<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Actor — null for system/CLI actions (seeders, scheduled jobs)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_role', 50)->nullable();

            // What happened
            $table->string('action', 50);           // created | updated | deleted | status_changed
            $table->string('auditable_type', 255);  // fully-qualified model class
            $table->unsignedBigInteger('auditable_id');

            // State diff — sensitive fields stripped before storage (see Auditable trait)
            $table->json('before')->nullable();
            $table->json('after')->nullable();

            // Request context — captured at dispatch time, before the job runs
            $table->string('ip_address', 45)->nullable();  // IPv6 max 45 chars
            $table->string('user_agent', 500)->nullable();

            // Append-only: created_at only, no updated_at
            $table->timestamp('created_at')->useCurrent();

            // Indexes for the audit viewer (US-17.1)
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
