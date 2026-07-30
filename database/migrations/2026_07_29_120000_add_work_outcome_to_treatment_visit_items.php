<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 · Slice 2.4b — CLINICAL WORK CAPTURE.
 *
 * Records WHAT HAPPENED TODAY to one planned treatment, at one visit.
 *
 * This is a FACT, not a status. It is never updated to reflect later reality:
 * an RCT across three appointments produces three rows —
 *
 *     visit 1 → started
 *     visit 2 → worked_on
 *     visit 3 → completed_today
 *
 * — and all three remain true forever. Nothing in this slice derives plan
 * completion, "ongoing", Converted, recall or analytics from these rows. That
 * is deliberately deferred: 2.4a proved the system cannot yet know what
 * treatment was done, and the fix for that is asking the clinician, not
 * inferring from billing.
 *
 * NULL is legitimate and common — ad-hoc work ("Other"), or a visit item
 * recorded before this slice existed. Nothing is backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_visit_items', function (Blueprint $table) {
            if (! Schema::hasColumn('treatment_visit_items', 'work_outcome')) {
                $table->enum('work_outcome', [
                    'started',          // first time this treatment was worked on
                    'worked_on',        // continued; not finished
                    'completed_today',  // finished at this visit
                ])->nullable()->after('treatment_plan_item_id');

                $table->index('work_outcome');
            }
        });
    }

    public function down(): void
    {
        Schema::table('treatment_visit_items', function (Blueprint $table) {
            if (Schema::hasColumn('treatment_visit_items', 'work_outcome')) {
                $table->dropIndex(['work_outcome']);
                $table->dropColumn('work_outcome');
            }
        });
    }
};
