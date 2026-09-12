<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4 — drop clinical_files.needs_review.
 *
 * Added in Phase 8 as an explicitly TEMPORARY flag: the data migration set it on
 * rows whose treatment_name could not be resolved to a TreatmentVisit, so a human
 * could come back to them. That review happened; the column has since been absent
 * from ClinicalFile's $fillable, absent from every query, and read by nothing —
 * grep across app/, resources/ and routes/ returns only the migration that made it.
 *
 * It is dropped rather than left alone because a column nothing reads is a
 * standing invitation for someone to start reading it and assume it means
 * something current.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clinical_files', 'needs_review')) {
            return;
        }

        Schema::table('clinical_files', function (Blueprint $table) {
            $table->dropColumn('needs_review');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('clinical_files', 'needs_review')) {
            return;
        }

        Schema::table('clinical_files', function (Blueprint $table) {
            $table->boolean('needs_review')
                  ->default(false)
                  ->after('tags')
                  ->comment('Temp Phase 8 flag: treatment_name unresolvable — needs manual visit link');
        });
    }
};
