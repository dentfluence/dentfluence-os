<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add visit_type column to treatment_visits.
     * Used by the Daily Huddle to classify each visit (RCT, Implant, Scaling, etc.).
     */
    public function up(): void
    {
        Schema::table('treatment_visits', function (Blueprint $table) {
            if (! Schema::hasColumn('treatment_visits', 'visit_type')) {
                // Free-text type label: 'RCT', 'Implant', 'Scaling', 'Filling',
                // 'Extraction', 'Crown', 'Consultation', etc.
                $table->string('visit_type')->nullable()->after('visit_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('treatment_visits', function (Blueprint $table) {
            if (Schema::hasColumn('treatment_visits', 'visit_type')) {
                $table->dropColumn('visit_type');
            }
        });
    }
};
