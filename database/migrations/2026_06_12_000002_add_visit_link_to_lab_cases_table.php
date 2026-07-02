<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link lab cases back to the treatment visit that created them.
 * Nullable — lab cases can still be created independently from the lab module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_cases', function (Blueprint $table) {
            $table->foreignId('treatment_visit_id')
                  ->nullable()
                  ->after('patient_id')
                  ->constrained('treatment_visits')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lab_cases', function (Blueprint $table) {
            $table->dropForeign(['treatment_visit_id']);
            $table->dropColumn('treatment_visit_id');
        });
    }
};
