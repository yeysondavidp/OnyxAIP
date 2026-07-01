<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // All windows are business hours — one shared typed representation (§10.1).
            $table->unsignedInteger('acknowledgement_hours');
            $table->unsignedInteger('onsite_response_metro_hours');
            $table->unsignedInteger('onsite_response_regional_hours');
            $table->unsignedInteger('resolution_hours');
            $table->string('monitoring_coverage'); // MonitoringCoverage enum
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // FK now that sla_profiles exists — clients.sla_profile_id was added nullable in
        // Sprint 2 (2026_06_29_232510) pending this table (US-12.1).
        Schema::table('clients', function (Blueprint $table) {
            $table->foreign('sla_profile_id', 'clients_sla_profile_id_fk')
                ->references('id')->on('sla_profiles')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign('clients_sla_profile_id_fk');
        });

        Schema::dropIfExists('sla_profiles');
    }
};
