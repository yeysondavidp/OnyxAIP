<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Rename to match SRA §3.1 naming convention
            $table->renameColumn('name', 'client_name');

            // §3.1 additional fields
            $table->string('primary_contact')->nullable()->after('client_code');
            $table->string('primary_email')->nullable()->after('primary_contact');
            $table->text('notes')->nullable()->after('primary_email');

            // sla_profile_id — nullable; FK constraint added when US-12.1 creates sla_profiles
            $table->unsignedBigInteger('sla_profile_id')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['primary_contact', 'primary_email', 'notes', 'sla_profile_id']);
            $table->renameColumn('client_name', 'name');
        });
    }
};
