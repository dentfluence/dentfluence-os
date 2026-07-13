<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 refinement — per-item consent toggle.
 * See docs/gap-analysis-treatment-planning-knowledge-bank.md.
 *
 * Not every treatment needs a consent form (e.g. Scaling). Default is FALSE
 * so existing rows stay silent (opt-in, additive), and new items default
 * from the treatment's existing `consent_required` TreatmentRule when picked
 * from the autocomplete — staff can still override per item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->boolean('consent_required')->default(false)->after('material_variants');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->dropColumn('consent_required');
        });
    }
};
