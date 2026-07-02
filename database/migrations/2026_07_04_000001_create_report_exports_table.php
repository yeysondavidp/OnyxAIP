<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tracks every generated report file for signed-URL lookup, "ready" notification,
        // and TTL-based pruning (EPIC-14). client_id is nullable — Technician Hours has no
        // client dimension (US-14.4 AC: "scoped to ONYX's jobs only").
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->string('report_type', 40);
            $table->string('format', 10);
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->json('params');
            $table->string('status', 20)->default('processing');
            $table->string('disk', 20)->default('local');
            $table->string('path')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('status');
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
