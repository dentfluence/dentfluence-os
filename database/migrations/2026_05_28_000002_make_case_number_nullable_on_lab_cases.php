<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The lab_cases table has a case_number column added manually
 * that is NOT NULL with no default. Make it nullable so the
 * model can auto-generate it on creation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_cases', function (Blueprint $table) {
            if (Schema::hasColumn('lab_cases', 'case_number')) {
                // Column was manually added — just make it nullable
                $table->string('case_number')->nullable()->change();
            } else {
                // Fresh install — add it as nullable so the model can auto-generate it
                $table->string('case_number')->nullable()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lab_cases', function (Blueprint $table) {
            if (Schema::hasColumn('lab_cases', 'case_number')) {
                $table->string('case_number')->nullable(false)->change();
            }
        });
    }
};
