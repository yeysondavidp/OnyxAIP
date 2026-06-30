<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // Rename to match SRA §3.2 naming convention
            $table->renameColumn('name', 'store_name');

            // §3.2 additional fields — added after existing columns
            $table->string('store_type')->after('store_name');
            $table->string('address_line1')->after('store_type');
            $table->string('suburb')->after('address_line1');
            $table->string('state', 10)->after('suburb');
            $table->string('postcode', 10)->after('state');
            $table->string('country', 60)->default('Australia')->after('postcode');
            $table->string('store_timezone', 60)->after('country');
            $table->string('store_manager_name')->nullable()->after('store_timezone');
            $table->string('store_manager_phone', 30)->nullable()->after('store_manager_name');
            $table->string('store_manager_email')->nullable()->after('store_manager_phone');
            $table->text('notes')->nullable()->after('store_manager_email');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'store_type', 'address_line1', 'suburb', 'state', 'postcode',
                'country', 'store_timezone', 'store_manager_name',
                'store_manager_phone', 'store_manager_email', 'notes',
            ]);
            $table->renameColumn('store_name', 'name');
        });
    }
};
