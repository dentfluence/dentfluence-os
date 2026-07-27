<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 · Slice 2.3b — PATIENT DECISION TRUTH.
 *
 * An APPEND-ONLY ledger of what the patient decided about a presented plan.
 * Rows are never updated or deleted: a patient who defers in July and accepts
 * in August produces TWO rows, and both remain. The plan's current decision is
 * DERIVED as the latest row, never stored as a mutable status.
 *
 * This exists because treatment_plans.status could not carry this truth:
 * Cancelled / Deferred / Rejected / Not Yet Decided are semantically distinct
 * and must never be collapsed into one enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_decisions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('treatment_plan_id')
                  ->constrained('treatment_plans')
                  ->cascadeOnDelete();

            // The patient's decision. 'partially_accepted' is elaborated by
            // per-item rows in plan_decision_items.
            $table->enum('decision', [
                'accepted',
                'partially_accepted',
                'deferred',
                'rejected',
            ]);

            // Deferred only. NULLABLE BY DESIGN — "the patient will think about
            // it" with no agreed review date is legitimate. Never invent one.
            $table->date('defer_until')->nullable();

            $table->text('notes')->nullable();

            // Channel the decision arrived through. Kept open so a future
            // patient microsite can record 'microsite' WITHOUT becoming a
            // second decision store — it must call the same domain service.
            $table->string('source', 32)->default('clinic');

            // The staff member who recorded it. Nullable because a future
            // patient-facing channel has no clinic actor.
            $table->foreignId('recorded_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            // Deriving "the current decision" = latest row for a plan.
            $table->index(['treatment_plan_id', 'created_at']);
            $table->index('decision');
            $table->index('defer_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_decisions');
    }
};
