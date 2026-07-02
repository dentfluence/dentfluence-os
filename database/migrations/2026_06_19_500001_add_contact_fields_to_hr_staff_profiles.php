<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_staff_profiles', function (Blueprint $table) {
            $table->string('whatsapp_number', 20)->nullable()->after('address');
            $table->string('alternate_phone', 20)->nullable()->after('whatsapp_number');
            $table->string('alternate_email', 255)->nullable()->after('alternate_phone');
        });
    }

    public function down(): void
    {
        Schema::table('hr_staff_profiles', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_number', 'alternate_phone', 'alternate_email']);
        });
    }
};
