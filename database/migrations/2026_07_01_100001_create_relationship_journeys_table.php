<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Relationship Engine Foundation
 * Create the `relationship_journeys` table.
 *
 * A journey is one segment of the relationship lifecycle. One relationship
 * can have multiple journeys (one lead journey, many opportunity journeys,
 * one recall journey running forever, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relationship_journeys', function (Blueprint $table) {
            $table->id();

            $table->foreignId('relationship_id')
                  ->constrained('relationships')
                  ->cascadeOnDelete();

            // What kind of journey is this?
            $table->enum('type', [
                'lead',
                'treatment',
                'recall',
                'opportunity',
                'membership',
                'referral',
            ])->index();

            // Current state within the journey (validated by RelationshipJourney::canTransitionTo)
            $table->string('state')->default('new_enquiry');

            // Arbitrary journey-specific payload (linked IDs, opportunity details, etc.)
            $table->json('metadata')->nullable();

            // Lifecycle timestamps for this journey
            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            // A relationship should not have two active journeys of the same type
            // (with the exception of 'opportunity' — see RelationshipJourney constants)
            $table->index(['relationship_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relationship_journeys');
    }
};
