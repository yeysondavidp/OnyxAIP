<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id');
            $table->string('status_before', 25)->nullable();
            $table->string('status_after', 25);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_role', 30)->nullable();
            $table->string('actor_label', 80)->nullable(); // e.g. "system:job_created"
            $table->text('reason')->nullable();
            $table->timestamp('transitioned_at');

            $table->index('asset_id');
            $table->index('transitioned_at');

            $table->foreign('asset_id', 'asset_history_asset_id_fk')
                ->references('id')->on('assets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_history');
    }
};
