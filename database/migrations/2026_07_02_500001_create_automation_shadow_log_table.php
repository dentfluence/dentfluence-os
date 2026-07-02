<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — Automation: shadow dual-run log.
 *
 * Records, per parity run, what the LEGACY recall path and the new AUTOMATION
 * path each decided for every candidate — WITHOUT touching communication_queue.
 * The parity command diffs the two sources to prove the Automation Engine
 * reproduces legacy behaviour before any cutover. Purely observational.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_shadow_log', function (Blueprint $table) {
            $table->id();

            // Groups all rows written by a single parity run.
            $table->uuid('run_id')->index();

            // Which automation surface / trigger this row is about (e.g. 'no_visit_6months').
            $table->string('trigger')->index();

            // 'legacy' = what RecallEngineService would do; 'automation' = what AutomationEngine decides.
            $table->string('source', 20)->index();

            // The candidate this decision is about.
            $table->unsignedBigInteger('patient_id')->nullable();

            // The recall purpose that would be queued (e.g. 'recall_no_visit').
            $table->string('purpose')->nullable();

            // 'queue' (would create a comm item) or 'suppress' (would not).
            $table->string('decision', 20);

            // Why suppressed (e.g. 'cooldown', 'duplicate_open') — null when queued.
            $table->string('reason')->nullable();

            $table->timestamps();

            // Fast per-run, per-candidate comparison.
            $table->index(['run_id', 'trigger', 'patient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_shadow_log');
    }
};
