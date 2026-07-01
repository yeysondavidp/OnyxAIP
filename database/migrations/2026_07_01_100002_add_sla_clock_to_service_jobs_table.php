<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_jobs', function (Blueprint $table) {
            // Snapshot of the profile in effect at clock-start (US-12.2) — historical jobs
            // keep their original targets even if the client's profile changes later.
            $table->unsignedBigInteger('sla_profile_id')->nullable()->after('client_id');
            // Fixed UTC instants computed once via BusinessHoursCalculator at job creation —
            // avoids re-walking business hours on every scheduled recompute (§10.2).
            $table->timestamp('sla_clock_started_at')->nullable()->after('sla_profile_id');
            $table->timestamp('sla_resolution_target_at')->nullable()->after('sla_clock_started_at');
            $table->timestamp('sla_at_risk_at')->nullable()->after('sla_resolution_target_at');
            $table->boolean('sla_at_risk')->default(false)->index()->after('sla_breached');

            $table->foreign('sla_profile_id', 'sj_sla_profile_id_fk')
                ->references('id')->on('sla_profiles')
                ->nullOnDelete();

            $table->index('sla_resolution_target_at');
        });
    }

    public function down(): void
    {
        Schema::table('service_jobs', function (Blueprint $table) {
            $table->dropForeign('sj_sla_profile_id_fk');
            $table->dropIndex(['sla_resolution_target_at']);
            $table->dropColumn([
                'sla_profile_id',
                'sla_clock_started_at',
                'sla_resolution_target_at',
                'sla_at_risk_at',
                'sla_at_risk',
            ]);
        });
    }
};
