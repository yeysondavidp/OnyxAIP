<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_screen_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')
                ->constrained('assets')
                ->cascadeOnDelete();
            $table->decimal('screen_size_inches', 5, 1);
            $table->unsignedSmallInteger('resolution_width');
            $table->unsignedSmallInteger('resolution_height');
            $table->string('orientation', 15);     // Orientation enum
            $table->string('mount_type');
            $table->string('totem_supplied_by', 15); // TotemSuppliedBy enum
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_screen_details');
    }
};
