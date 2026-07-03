<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — Workflow Engine, Slice 1: engine core (dormant scaffolding).
 *
 * A `workflow_instances` row is one RUN of a template for one
 * relationship/patient — e.g. "this patient's RCT on tooth 36, currently on
 * the obturation step". `subject_type`/`subject_id` is a polymorphic pointer
 * at whatever real record the run is about (a TreatmentPlan, a specific
 * TreatmentVisit chain, etc.) so the engine isn't hard-wired to one model.
 *
 * `relationship_id` is nullable (not a hard FK) because not every patient is
 * yet linked to a Relationship — `identity.link_patient` is still off by
 * default (Phase 1). Same soft-link pattern as `today_actions.relationship_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('template_id')
                ->constrained('workflow_templates')
                ->cascadeOnDelete();

            // Soft link — see class docblock. No FK constraint on purpose.
            $table->unsignedBigInteger('relationship_id')->nullable()->index();

            // Polymorphic pointer at the real record this run tracks
            // (e.g. 'App\Models\TreatmentPlan' + its id).
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // The step `key` (from the template's `steps` json) the run is on now.
            $table->string('current_step');

            // active | completed | abandoned
            $table->string('status', 20)->default('active');

            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();

            // Free-form run context (e.g. which tooth, which doctor started it).
            $table->json('context')->nullable();

            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['template_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instances');
    }
};
