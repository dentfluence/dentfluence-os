<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add lab-linkage columns to treatments.
 *
 * needs_lab         — if true, selecting this treatment in a visit
 *                     triggers the "Lab Case Addition" prompt.
 * lab_work_category — pre-fills the work category on the lab case form
 *                     (must be one of LabCase::WORK_CATEGORIES keys).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->boolean('needs_lab')->default(false)->after('is_active');
            $table->string('lab_work_category')->nullable()->after('needs_lab');
        });
    }

    public function down(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->dropColumn(['needs_lab', 'lab_work_category']);
        });
    }
};
