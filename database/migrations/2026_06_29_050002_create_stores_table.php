<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();

            // ── Tenant scope (required pattern for ClientScoped models) ──
            // foreignId = unsigned bigint NOT NULL
            // constrained = FK → clients.id, ON DELETE RESTRICT
            // index = speeds up WHERE client_id = ? queries (§14.4)
            $table->foreignId('client_id')->constrained()->index();

            $table->string('name');
            $table->string('store_code', 20)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
