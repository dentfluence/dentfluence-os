<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — Workflow Engine, Slice 1: engine core (dormant scaffolding).
 *
 * One row per step a `workflow_instances` run has passed through — the
 * audit trail behind WorkflowEngine::status(). `exited_at` stays null while
 * a step is the current one; it's stamped the moment advance() moves the
 * run past it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_step_log', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workflow_instance_id')
                ->constrained('workflow_instances')
                ->cascadeOnDelete();

            // The step `key` (from the template's `steps` json).
            $table->string('step');

            $table->timestamp('entered_at');
            $table->timestamp('exited_at')->nullable();

            // Who advanced the run to this step (nullable — Slice 1 has no
            // callers yet, so this may be null or a system actor later).
            $table->unsignedBigInteger('actor_id')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['workflow_instance_id', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_step_log');
    }
};
