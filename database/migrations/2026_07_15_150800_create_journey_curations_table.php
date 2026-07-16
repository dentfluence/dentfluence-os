<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Case Acceptance Engine — per-node doctor curation (frozen §5.4, decision #8).
 * Kept as a RELATIONAL table (not JSON) for analytics/reporting. Rows become
 * immutable once the journey is sent (part of the pinned set).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journey_curations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_journey_id')->constrained('patient_journeys')->cascadeOnDelete();
            $table->foreignId('decision_tree_node_id')->constrained('decision_tree_nodes')->cascadeOnDelete();
            $table->boolean('visible')->default(true);
            $table->boolean('is_recommended')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['patient_journey_id', 'decision_tree_node_id'], 'journey_curations_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_curations');
    }
};
