<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Treatment Visit form fix — multi-select plan items + partial tooth pick.
 *
 * treatment_visit_items.tooth_number was left at VARCHAR(20) when
 * invoice_items and treatment_plan_items were widened to VARCHAR(100) in
 * 2026_07_02_100007. Now that a visit's billing line can inherit a
 * multi-tooth plan item's tooth_number directly (e.g. "24, 25, 26, 27, 28"),
 * it needs the same width or long combinations get truncated/rejected.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('treatment_visit_items', 'tooth_number')) {
            DB::statement('ALTER TABLE treatment_visit_items MODIFY tooth_number VARCHAR(100) NULL');
        }
    }

    public function down(): void
    {
        // Leave widened — narrowing could truncate existing data.
    }
};
