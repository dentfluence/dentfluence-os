<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an editable per-treatment-type recall periodicity (days).
 *
 * Deliberately NOT adding a per-treatment-type template — the project's
 * "avoid feature bloat" rule means treatment-wise recall reuses the ONE
 * type=recall MessageTemplate (with tokens) for every treatment type; only
 * the periodicity (when to recall) is treatment-specific, not the message
 * copy. Null = "not configured", falls back to the general recall
 * periodicity in AppSetting (recall.general_days).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_types', function (Blueprint $table) {
            if (!Schema::hasColumn('treatment_types', 'recall_after_days')) {
                $table->unsignedInteger('recall_after_days')->nullable()->after('base_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('treatment_types', function (Blueprint $table) {
            if (Schema::hasColumn('treatment_types', 'recall_after_days')) {
                $table->dropColumn('recall_after_days');
            }
        });
    }
};
