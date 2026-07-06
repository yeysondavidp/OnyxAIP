<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_jobs', function (Blueprint $table) {
            $table->boolean('is_flexible')->default(false)->after('early_start_window');
        });

        // Backfill existing rows that were only ever inferable as flexible from
        // having no scheduled date/time (the pre-column behaviour this replaces).
        DB::table('service_jobs')
            ->whereNull('scheduled_date')
            ->whereNull('scheduled_time')
            ->update(['is_flexible' => true]);
    }

    public function down(): void
    {
        Schema::table('service_jobs', function (Blueprint $table) {
            $table->dropColumn('is_flexible');
        });
    }
};
