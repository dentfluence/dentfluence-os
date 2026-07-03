<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — Workflow Engine, Slice 1: engine core (dormant scaffolding).
 *
 * A `workflow_templates` row is the DEFINITION of a multi-step sequence
 * (e.g. "RCT staging"). It does NOT track any particular patient's progress
 * — that's `workflow_instances`. Additive only, nothing existing touched.
 *
 * `steps` is an ordered JSON array, minimum shape per step:
 *   { "key": "access", "label": "Access Opening", "min_gap_days_from_previous": 0 }
 * Deliberately linear-only for v1 (see docs/phase-5/workflow-engine-proposal.md)
 * — no branching/conditional steps until a real template needs it.
 *
 * This migration also seeds the one template Slice 1 ships with:
 * `rct_staging`, matching the exact stage keys/labels already used by the
 * "Root Canal Treatment" record in `treatments.stages` (see
 * DentalTreatmentsMasterSeeder) so a later shadow-run (Slice 2) can compare
 * apples to apples against what doctors already type into
 * TreatmentVisit::current_stage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_templates', function (Blueprint $table) {
            $table->id();

            // Machine key used by WorkflowEngine::start(), e.g. "rct_staging".
            $table->string('key')->unique();

            // Human label, e.g. "RCT Staging".
            $table->string('name');

            // Bump when steps change shape so old instances keep pointing at
            // the version they actually started under.
            $table->unsignedInteger('version')->default(1);

            // Ordered step definitions — see class docblock for shape.
            $table->json('steps');

            // Templates can be retired without deleting history.
            $table->boolean('active')->default(true);

            $table->timestamps();
        });

        // ── Seed: rct_staging ───────────────────────────────────────────
        // Mirrors treatments.stages for "Root Canal Treatment" (code RCT-01):
        // diagnosis -> access -> instrumentation -> obturation -> review -> crown.
        //
        // min_gap_days_from_previous values below are PLACEHOLDER estimates
        // (clinically reasonable, not measured from real visit-interval data
        // — this sandbox has no DB access to check). They are advisory only:
        // nothing in Slice 1 enforces them. Refine using the Slice 2
        // shadow-run parity output before treating them as authoritative.
        DB::table('workflow_templates')->insert([
            'key'        => 'rct_staging',
            'name'       => 'RCT Staging',
            'version'    => 1,
            'steps'      => json_encode([
                ['key' => 'diagnosis',      'label' => 'Diagnosis & X-ray',    'min_gap_days_from_previous' => 0],
                ['key' => 'access',         'label' => 'Access Opening',       'min_gap_days_from_previous' => 0],
                ['key' => 'instrumentation','label' => 'Canal Preparation',    'min_gap_days_from_previous' => 0],
                ['key' => 'obturation',     'label' => 'Obturation',           'min_gap_days_from_previous' => 3],
                ['key' => 'review',         'label' => 'Post-op Review',       'min_gap_days_from_previous' => 7],
                ['key' => 'crown',          'label' => 'Crown Placement',      'min_gap_days_from_previous' => 14],
            ]),
            'active'     => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_templates');
    }
};
