<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SOS dosage amount — previously `is_sos` was a bare flag ("give as needed")
 * with no way to record how much per SOS dose (e.g. "5 ml SOS" for a syrup,
 * "1 tab SOS" for a tablet). Adds a single nullable amount column reused for
 * every form type; unit (ml vs plain count) is inferred the same way the
 * Morn/Noon/Night cells already are, via PrescriptionItem::isLiquidDose().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->decimal('sos_dose', 6, 2)->nullable()->after('is_sos');
        });
    }

    public function down(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropColumn('sos_dose');
        });
    }
};
