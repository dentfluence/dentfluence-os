<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab Module — Shade Guide System
 *
 * Adds shade_guide to lab_case_items so a shade code (e.g. "A2" or "2M2")
 * is unambiguous about which system it belongs to: Vita Classical
 * (A1-D4, BL1-BL4) or Vita 3D Master (1M1-5M3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_case_items', function (Blueprint $table) {
            $table->string('shade_guide', 20)->nullable()->after('shade');
        });
    }

    public function down(): void
    {
        Schema::table('lab_case_items', function (Blueprint $table) {
            $table->dropColumn('shade_guide');
        });
    }
};
