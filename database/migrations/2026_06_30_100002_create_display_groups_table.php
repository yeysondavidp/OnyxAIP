<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('display_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->string('group_name');
            // UNIQUE on player_asset_id enforces one-group-per-player at DB level (§ US-05.1)
            $table->unsignedBigInteger('player_asset_id')->unique();
            $table->string('layout_description', 500)->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('store_id');

            $table->foreign('store_id', 'display_groups_store_id_fk')
                ->references('id')->on('stores')->cascadeOnDelete();

            $table->foreign('player_asset_id', 'display_groups_player_asset_id_fk')
                ->references('id')->on('assets')->restrictOnDelete();
        });

        // Pivot: one screen can belong to exactly one group (UNIQUE on asset_id)
        Schema::create('display_group_screens', function (Blueprint $table) {
            $table->unsignedBigInteger('display_group_id');
            $table->unsignedBigInteger('asset_id');

            $table->primary(['display_group_id', 'asset_id']);
            // The UNIQUE constraint on asset_id alone enforces one-group-per-screen at DB level
            $table->unique('asset_id', 'display_group_screens_asset_id_unique');

            $table->foreign('display_group_id', 'dgs_display_group_id_fk')
                ->references('id')->on('display_groups')->cascadeOnDelete();

            $table->foreign('asset_id', 'dgs_asset_id_fk')
                ->references('id')->on('assets')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('display_group_screens');
        Schema::dropIfExists('display_groups');
    }
};
