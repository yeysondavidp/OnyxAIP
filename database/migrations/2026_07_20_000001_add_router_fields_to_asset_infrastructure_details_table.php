<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_infrastructure_details', function (Blueprint $table) {
            $table->string('imei', 20)->nullable();
            $table->string('mac_address', 17)->nullable();
            $table->string('wifi_ssid', 100)->nullable();
            // text, not string: holds Laravel's encrypted-cast ciphertext, not the raw value
            $table->text('wifi_password')->nullable();
            $table->text('admin_password')->nullable();
            $table->string('sim_carrier', 50)->nullable();
            $table->string('sim_number', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('asset_infrastructure_details', function (Blueprint $table) {
            $table->dropColumn([
                'imei',
                'mac_address',
                'wifi_ssid',
                'wifi_password',
                'admin_password',
                'sim_carrier',
                'sim_number',
            ]);
        });
    }
};
