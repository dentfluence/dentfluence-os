<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill patient_id on treatment_plans that were created before the
     * 2026_05_27 upgrade migration added the patient_id column.
     *
     * Those rows have patient_id = NULL and are invisible to the
     * Patient::treatmentPlans() relationship, causing the visit-form
     * Treatment Plan dropdown to appear empty.
     *
     * Derives patient_id from the linked consultation's patient_id.
     */
    public function up(): void
    {
        DB::statement("
            UPDATE treatment_plans tp
            JOIN consultations c ON c.id = tp.consultation_id
            SET tp.patient_id = c.patient_id
            WHERE tp.patient_id IS NULL
              AND c.patient_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        // Intentionally left empty — we don't want to re-null patient_id on rollback.
    }
};
