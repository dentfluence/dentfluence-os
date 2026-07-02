<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen treatment_plan_items.tooth_number from VARCHAR(20) to VARCHAR(100).
 *
 * Reason: a single treatment row can now reference MULTIPLE teeth
 * (e.g. "16, 17, 26, 27, 36, 37"), which can exceed 20 characters.
 * MySQL MODIFY is used directly so this works on Laragon without
 * requiring doctrine/dbal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('treatment_plan_items', 'tooth_number')) {
            DB::statement('ALTER TABLE treatment_plan_items MODIFY tooth_number VARCHAR(100) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('treatment_plan_items', 'tooth_number')) {
            DB::statement('ALTER TABLE treatment_plan_items MODIFY tooth_number VARCHAR(20) NULL');
        }
    }
};
