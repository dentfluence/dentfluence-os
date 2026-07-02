<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // 'doctor' | 'staff'  — used to group roles in the UI
            $table->string('category', 20)->default('staff')->after('slug');
        });

        // Tag the Doctor role immediately so existing installs are correct
        DB::table('roles')->where('slug', 'doctor')->update(['category' => 'doctor']);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
