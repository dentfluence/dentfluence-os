<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2 — indexes the universal search leans on.
 *
 * Purely additive: three indexes, no column changes, no data touched.
 *
 * `tooth_number` is the one Sumit asked for first — "give me every file on 26"
 * is the question this module exists to answer, and it was a full table scan.
 * `procedure` and `file_type` back the other two filters that appear on almost
 * every query. file_type already sat inside (patient_id, file_type), but MySQL
 * can only use the leftmost column of a composite, so filtering by type alone
 * never touched it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_files', function (Blueprint $table) {
            $table->index('tooth_number', 'cf_tooth_number_idx');
            $table->index('procedure', 'cf_procedure_idx');
            $table->index('file_type', 'cf_file_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('clinical_files', function (Blueprint $table) {
            $table->dropIndex('cf_tooth_number_idx');
            $table->dropIndex('cf_procedure_idx');
            $table->dropIndex('cf_file_type_idx');
        });
    }
};
