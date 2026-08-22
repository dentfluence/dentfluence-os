<?php

// =============================================================================
// prescriptions.diagnosis — VARCHAR(255) → TEXT
// -----------------------------------------------------------------------------
// Tulip, 22-Aug-2026: saving a consultation with a long Provisional Diagnosis
// AND a drug in the embedded prescription panel returned a 500.
//
// consultations.provisional_diagnosis is TEXT, so the textarea accepted it. The
// failure was downstream: ConsultationController copies the diagnosis onto the
// linked Prescription, and prescriptions.diagnosis was declared
// `$table->string('diagnosis')` — VARCHAR(255). In MySQL strict mode that is a
// "Data too long for column 'diagnosis'" QueryException, i.e. a 500, and only
// ever when a drug was entered (the copy sits behind panelHasDrugRows()).
//
// The call sites are ALSO now Str::limit()-guarded, so this can never 500 again
// even on a database where this migration has not run. Belt and braces: the
// guard protects old databases, this column protects the actual content.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('prescriptions', 'diagnosis')) {
            // MODIFY is naturally idempotent — re-running is a no-op.
            DB::statement('ALTER TABLE prescriptions MODIFY diagnosis TEXT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('prescriptions', 'diagnosis')) {
            // Truncate first so the narrowing cannot fail on existing rows.
            DB::statement('UPDATE prescriptions SET diagnosis = LEFT(diagnosis, 255) WHERE CHAR_LENGTH(diagnosis) > 255');
            DB::statement('ALTER TABLE prescriptions MODIFY diagnosis VARCHAR(255) NULL');
        }
    }
};
