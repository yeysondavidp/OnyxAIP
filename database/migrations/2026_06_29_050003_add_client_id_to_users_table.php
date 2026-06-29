<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Null for PM (unrestricted). Set for client_user role (v2, EPIC-01).
            $table->foreignId('client_id')->nullable()->after('id')
                  ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Client::class);
            $table->dropColumn('client_id');
        });
    }
};
