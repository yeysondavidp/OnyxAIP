<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_lightbox_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')
                ->constrained('assets')
                ->cascadeOnDelete();
            $table->string('lightbox_dimensions');         // W x H x D in mm
            $table->string('light_type', 20);              // LightType enum
            $table->string('content_change_frequency', 20); // ContentChangeFrequency enum
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_lightbox_details');
    }
};
