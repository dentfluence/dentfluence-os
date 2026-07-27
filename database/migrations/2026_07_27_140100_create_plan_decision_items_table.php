<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 · Slice 2.3b — PER-ITEM DECISION (partial acceptance).
 *
 * One row per treatment item the patient decided on, so the truth
 *
 *     Implant    → accepted
 *     Crown      → deferred
 *     Scaling    → rejected
 *     Bone graft → not yet decided
 *
 * is independently queryable. Deliberately NOT a JSON blob or a comma-separated
 * id set: "which patients deferred a crown" must be answerable in SQL.
 *
 * CRITICAL: treatment_plan_items.status='cancelled' must NEVER be used to mean
 * rejected or deferred. Cancelled is an administrative/execution state.
 * Rejected, Deferred and Not Yet Decided are patient decisions. Different truths.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_decision_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plan_decision_id')
                  ->constrained('plan_decisions')
                  ->cascadeOnDelete();

            $table->foreignId('treatment_plan_item_id')
                  ->constrained('treatment_plan_items')
                  ->cascadeOnDelete();

            // 'not_yet_decided' is a real, recordable answer — it means the
            // patient was asked about this item and has not decided, which is
            // NOT the same as never having been asked.
            $table->enum('decision', [
                'accepted',
                'deferred',
                'rejected',
                'not_yet_decided',
            ]);

            $table->timestamps();

            // One verdict per item per decision event (the decision itself is
            // append-only, so a later change of mind creates a NEW decision).
            $table->unique(['plan_decision_id', 'treatment_plan_item_id'], 'plan_decision_item_unique');
            $table->index('treatment_plan_item_id');
            $table->index('decision');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_decision_items');
    }
};
