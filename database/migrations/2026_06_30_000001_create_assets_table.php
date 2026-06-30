<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 40)->unique();
            $table->string('asset_type', 30);          // AssetType enum
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('store_id');
            $table->string('asset_name');
            $table->string('manufacturer');
            $table->string('model');
            $table->string('serial_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->date('install_date')->nullable();
            $table->string('asset_status', 25)->default('active'); // AssetStatus enum
            $table->string('location_notes')->nullable();
            // Nullable self-referencing FK — nullOnDelete so deleting a parent doesn't orphan children
            $table->unsignedBigInteger('parent_asset_id')->nullable();
            $table->text('notes')->nullable();
            $table->index('client_id');
            $table->index('store_id');
            $table->index('asset_status');
            $table->timestamps();

            $table->foreign('client_id', 'assets_client_id_fk')->references('id')->on('clients')->restrictOnDelete();
            $table->foreign('store_id', 'assets_store_id_fk')->references('id')->on('stores')->restrictOnDelete();
            $table->foreign('parent_asset_id', 'assets_parent_asset_id_fk')->references('id')->on('assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
