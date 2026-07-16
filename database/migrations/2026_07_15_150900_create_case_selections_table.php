<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Case Acceptance Engine — patient selections (frozen §5.5).
 * The mutable "cart" until accept. Running estimate is recomputed on the fly
 * (CaseSelectionService), never stored here. `treatment_option_id` references
 * the Treatment Module (nullable — a node may be a plain choice with no priced
 * option).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_journey_id')->constrained('patient_journeys')->cascadeOnDelete();
            $table->foreignId('decision_tree_node_id')->constrained('decision_tree_nodes')->cascadeOnDelete();
            $table->foreignId('treatment_option_id')->nullable()
                  ->constrained('treatment_options')->nullOnDelete();
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();

            $table->unique(['patient_journey_id', 'decision_tree_node_id'], 'case_selections_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_selections');
    }
};
