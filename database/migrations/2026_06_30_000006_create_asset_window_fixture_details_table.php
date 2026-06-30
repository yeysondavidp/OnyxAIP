<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_window_fixture_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')
                ->constrained('assets')
                ->cascadeOnDelete();
            // Lean per YAGNI — §4.3 mandates no rigid spec fields for Window Fixtures.
            // Captures custom-fabricated dimensions (e.g. "1500x1110 mm").
            $table->string('fixture_dimensions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_window_fixture_details');
    }
};
