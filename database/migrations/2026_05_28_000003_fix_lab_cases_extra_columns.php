<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The lab_cases table has several manually-added columns that are
 * NOT NULL with no default value. This migration makes them safe:
 *   - branch_id  → default(1)  (matches patients table convention)
 *   - created_by → nullable    (optional audit field)
 *
 * Uses hasColumn() guards so it's idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_cases', function (Blueprint $table) {
            if (Schema::hasColumn('lab_cases', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->default(1)->change();
            }

            if (Schema::hasColumn('lab_cases', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // No destructive rollback needed — just leave columns as-is
    }
};
