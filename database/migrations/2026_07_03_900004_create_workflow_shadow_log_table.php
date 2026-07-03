<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — Workflow Engine, Slice 2: shadow-run parity log.
 *
 * One row per treatment-visit save where a shadow WorkflowInstance was
 * compared against what the doctor actually typed into
 * TreatmentVisit::current_stage. Purely observational — same pattern as
 * `automation_shadow_log` from Phase 2. Never read by anything that changes
 * behaviour; only by the Slice 4 parity report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_shadow_log', function (Blueprint $table) {
            $table->id();

            // Null if the instance itself couldn't be created/found (action = 'error').
            $table->foreignId('workflow_instance_id')
                ->nullable()
                ->constrained('workflow_instances')
                ->nullOnDelete();

            // Soft links — no hard FK, this is a diagnostic log, not a source of truth.
            $table->unsignedBigInteger('treatment_visit_id')->nullable()->index();
            $table->unsignedBigInteger('patient_id')->nullable()->index();

            // Which template this row is about, e.g. "rct_staging".
            $table->string('template_key')->index();

            // What the doctor actually typed/selected into current_stage.
            $table->string('doctor_stage')->nullable();

            // 'started' | 'noop' | 'advanced' | 'resynced' | 'diverged' | 'error'
            $table->string('action', 20);

            // True when the engine's view matched the doctor's without any
            // forced resync (started/noop/advanced). False for resynced/diverged/error.
            $table->boolean('agreed');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['template_key', 'agreed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_shadow_log');
    }
};
