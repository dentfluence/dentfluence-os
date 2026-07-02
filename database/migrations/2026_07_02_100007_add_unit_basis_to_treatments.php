<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chunk 4 — Treatment Master linkage + auto-quantity.
 *
 * 1. Adds `unit_basis` to treatments — drives how invoice quantity is derived
 *    from selected teeth:
 *       per_tooth   → qty = number of selected teeth (Composite on 24,25,26 = 3)
 *       whole_mouth → qty = 1 (Full-mouth scaling, whitening, consultation …)
 *       per_arch    → qty = 1 per arch (reserved; treated like whole_mouth for now)
 *    Best-effort seeds common whole-mouth treatments by name so the feature works
 *    out of the box; anything else defaults to per_tooth.
 *
 * 2. Widens tooth_number columns so a multi-tooth line (e.g. "24, 25, 26, 27, 28")
 *    fits comfortably.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->string('unit_basis', 20)->default('per_tooth')->after('gst_pct');
        });

        // Best-effort: mark obvious whole-mouth / per-visit treatments.
        DB::table('treatments')->where(function ($q) {
            foreach (['scal', 'polish', 'whiten', 'bleach', 'full mouth', 'consult', 'checkup', 'check-up', 'x-ray', 'xray', 'opg'] as $kw) {
                $q->orWhere('name', 'like', "%{$kw}%");
            }
        })->update(['unit_basis' => 'whole_mouth']);

        // Widen tooth columns for multi-tooth lines.
        DB::statement("ALTER TABLE invoice_items MODIFY tooth_number VARCHAR(100) NULL");
        DB::statement("ALTER TABLE treatment_plan_items MODIFY tooth_number VARCHAR(100) NULL");
    }

    public function down(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->dropColumn('unit_basis');
        });
        // Leave tooth_number widened — narrowing could truncate existing data.
    }
};
