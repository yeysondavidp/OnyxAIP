<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_infrastructure_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')
                ->constrained('assets')
                ->cascadeOnDelete();
            $table->string('cable_type')->nullable();
            $table->decimal('length', 8, 2)->nullable();
            // nullOnDelete: deleting a connected asset zeroes the link rather than orphaning the row
            $table->foreignId('connected_from_asset_id')
                ->nullable()
                ->constrained('assets')
                ->nullOnDelete();
            $table->foreignId('connected_to_asset_id')
                ->nullable()
                ->constrained('assets')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_infrastructure_details');
    }
};
