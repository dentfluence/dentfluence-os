<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 — Temp column: needs_review
 *
 * Added before data migration scripts run. Allows 8A to flag rows where
 * treatment_name could not be resolved to an existing TreatmentVisit.
 *
 * This column is TEMPORARY. Once all flagged records have been reviewed
 * and linked manually, this column will be dropped in a future cleanup phase.
 *
 * Queryable inside the app:
 *   ClinicalFile::where('needs_review', true)->get()
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_files', function (Blueprint $table) {
            $table->boolean('needs_review')
                  ->default(false)
                  ->after('tags')
                  ->comment('Temp Phase 8 flag: treatment_name unresolvable — needs manual visit link');
        });
    }

    public function down(): void
    {
        Schema::table('clinical_files', function (Blueprint $table) {
            $table->dropColumn('needs_review');
        });
    }
};
