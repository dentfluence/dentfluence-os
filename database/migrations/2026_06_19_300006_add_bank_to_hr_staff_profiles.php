<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_staff_profiles', function (Blueprint $table) {
            $table->string('bank_name', 100)->nullable()->after('notes');
            $table->string('account_holder_name', 255)->nullable()->after('bank_name');
            $table->string('account_number', 50)->nullable()->after('account_holder_name');
            $table->string('ifsc_code', 20)->nullable()->after('account_number');
            $table->string('branch_name', 150)->nullable()->after('ifsc_code');
        });
    }

    public function down(): void
    {
        Schema::table('hr_staff_profiles', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'account_holder_name', 'account_number', 'ifsc_code', 'branch_name']);
        });
    }
};
