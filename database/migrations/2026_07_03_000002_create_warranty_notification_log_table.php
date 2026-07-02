<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB-level idempotency for warranty-expiry notifications (US-13.3) — one row per
 * (asset, threshold) pair ever fired, so the daily scheduler can't double-send
 * even if it somehow runs twice for the same day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warranty_notification_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id')->index();
            $table->unsignedSmallInteger('threshold_days');
            $table->timestamp('notified_at');

            $table->foreign('asset_id', 'wnl_asset_id_fk')->references('id')->on('assets')->cascadeOnDelete();
            $table->unique(['asset_id', 'threshold_days'], 'wnl_asset_threshold_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_notification_log');
    }
};
