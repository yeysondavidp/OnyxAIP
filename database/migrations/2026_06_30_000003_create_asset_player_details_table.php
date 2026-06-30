<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_player_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')
                ->constrained('assets')
                ->cascadeOnDelete();
            $table->string('player_type', 30);  // PlayerType enum
            $table->string('cms_platform')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('mac_address', 17)->nullable();
            $table->string('firmware_version', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_player_details');
    }
};
