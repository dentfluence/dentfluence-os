<?php

// =============================================================================
// consultations.treatment_advised
// -----------------------------------------------------------------------------
// Tulip, 22-Aug-2026: "Treatment advised: RCT followed by core build-up and
// crown w.r.t. #17 …" was being typed into Examination Findings, because the
// case paper had nowhere else to put it. Findings are what was OBSERVED; advice
// is what was RECOMMENDED. Mixing them makes the clinical record unreadable and
// makes the advice impossible to surface anywhere but the printout.
//
// TEXT, nullable, additive. Encrypted at rest via the Consultation model cast,
// like every other clinical free-text field. No backfill: existing rows keep
// their advice inside examination findings, and the print simply omits the
// section when the column is empty.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('consultations', 'treatment_advised')) {
            Schema::table('consultations', function (Blueprint $table) {
                $table->text('treatment_advised')->nullable()->after('differential_diagnosis');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('consultations', 'treatment_advised')) {
            Schema::table('consultations', function (Blueprint $table) {
                $table->dropColumn('treatment_advised');
            });
        }
    }
};
