<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add staff_instruction column to appointments.
     * The original stub migration (2026_05_18_201650) was empty — this adds it properly.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Only add if not already present (safety guard)
            if (! Schema::hasColumn('appointments', 'staff_instruction')) {
                $table->text('staff_instruction')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'staff_instruction')) {
                $table->dropColumn('staff_instruction');
            }
        });
    }
};
