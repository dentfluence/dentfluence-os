<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 · Slice 2.2 — PLAN PRESENTATION TRUTH.
 *
 * "The plan was actually shown and explained to the patient" becomes a real
 * clinical fact. Until now it existed only as a SALES stage: markPresented()
 * moved the linked TreatmentOpportunity to 'quoted' and wrote nothing
 * clinical, so the clinical record could not answer "has this patient
 * actually seen this plan?" (Slice 2.1 finding, test-locked).
 *
 * Deliberately minimal — one nullable timestamp:
 *   NULL      = never presented (or historical, pre-2.2 — NOT backfilled)
 *   timestamp = when the patient was FIRST shown this plan
 *
 * Presented is NOT a decision. presented_at + no acceptance = Decision
 * Pending, which is a legitimate long-lived state; the decision model itself
 * lands in Slice 2.3 (plan_decisions / plan_decision_items).
 *
 * No journey_status column, no state-machine enum, no PRE duplication.
 * Reversible: down() drops only this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('treatment_plans', 'presented_at')) {
                // After accepted_at so the lifecycle reads in order in the schema.
                $table->timestamp('presented_at')->nullable()->after('accepted_at');
                $table->index('presented_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            if (Schema::hasColumn('treatment_plans', 'presented_at')) {
                $table->dropIndex(['presented_at']);
                $table->dropColumn('presented_at');
            }
        });
    }
};
