<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Relationship Engine Foundation
 * Create the universal `activities` table (ActivityEngine).
 *
 * Every meaningful thing that happens in the system writes a row here.
 * This table is the single source of truth for the unified Timeline
 * in Phase 3. It does NOT replace lead_activities yet — both tables
 * coexist until Phase 3 unification.
 *
 * Columns:
 *  subject     — the thing the event is about (polymorphic: Lead, Patient, Appointment, etc.)
 *  actor       — who caused it (polymorphic nullable: User, system = null)
 *  event       — string event key e.g. 'lead.created', 'appointment.booked', 'call.logged'
 *  relationship_id — nullable FK so the activity can be pulled into a Relationship Timeline
 *  occurred_at — when it happened (defaults to now() at write time)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();

            // Nullable — system-level events may not belong to a specific relationship yet
            $table->foreignId('relationship_id')
                  ->nullable()
                  ->constrained('relationships')
                  ->nullOnDelete();

            // What is the event about? (Lead, Patient, Appointment, Invoice, etc.)
            $table->morphs('subject');        // subject_type + subject_id

            // Who caused the event? (User, or null for automated/system actions)
            $table->nullableMorphs('actor');  // actor_type + actor_id

            // Structured event key — always in dot notation: 'lead.created', 'recall.queued'
            $table->string('event')->index();

            // Human-readable description (displayed on Timeline)
            $table->text('description')->nullable();

            // Any extra context: old stage, new stage, channel, value, etc.
            $table->json('metadata')->nullable();

            // Canonical timestamp — separate from created_at so we can backfill historical events
            $table->dateTime('occurred_at')->index();

            $table->timestamps();

            // Composite index for Timeline queries (relationship_id + occurred_at).
            // Note: subject_type+subject_id index is already created by morphs() above — do not duplicate.
            $table->index(['relationship_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
