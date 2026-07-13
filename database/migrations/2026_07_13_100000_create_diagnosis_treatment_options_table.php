<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Knowledge Bank MVP — see docs/gap-analysis-treatment-planning-knowledge-bank.md
 * Phase 1. Purely additive: new table only, no existing table touched.
 *
 * Ranks Treatment options per Diagnosis (diagnosis_masters, NOT the
 * per-consultation `diagnoses` table — see model docblock) so a dentist can
 * define once, ahead of time, "for this diagnosis, Treatment X is the
 * recommended option, Treatment Y is an acceptable alternative." Nothing
 * currently reads this table automatically — it's a standalone reference
 * asset for now (see PR notes for why the aiSuggest() refactor was
 * deliberately left out of this phase).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnosis_treatment_options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('diagnosis_id')
                  ->constrained('diagnosis_masters')
                  ->cascadeOnDelete();

            $table->foreignId('treatment_id')
                  ->constrained('treatments')
                  ->cascadeOnDelete();

            // Same vocabulary as treatment_plan_items.option_rank, so a
            // ranked row here maps 1:1 onto a plan item rank later.
            $table->enum('rank', ['best', 'acceptable', 'alternative'])->default('best');

            // Why this option is ranked this way for this diagnosis — shown
            // to the dentist populating/using the bank, not patient-facing.
            $table->text('notes')->nullable();

            // Display order within a diagnosis's option list.
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            // One row per (diagnosis, treatment) pair — re-ranking edits the
            // existing row instead of creating a duplicate.
            $table->unique(['diagnosis_id', 'treatment_id']);
            $table->index('diagnosis_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosis_treatment_options');
    }
};
