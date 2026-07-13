<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — Clinical Consent. See docs/gap-analysis-treatment-planning-knowledge-bank.md.
 * Purely additive: new table only, no existing table touched.
 *
 * One row per "Generate Consent" action on a treatment plan. Stores a merge
 * SNAPSHOT (patient/tooth/procedure + the consent text shown at that moment)
 * rather than a live pointer, because the underlying SOP consent_notes text
 * can be edited later — this row is the audit record of exactly what the
 * patient was shown and asked to sign, independent of later edits.
 *
 * Deliberately separate from the DPDP `patient_consents` / `consent_logs`
 * tables (data-privacy consent) — this is clinical procedure consent.
 * No e-signature capture (see gap-analysis "skip" list) — wet-ink only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_consents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('treatment_plan_id')
                  ->constrained('treatment_plans');

            // Denormalized for quick per-patient queries without a join,
            // consistent with how treatment_plans itself stores patient_id.
            $table->foreignId('patient_id')
                  ->constrained('patients');

            $table->foreignId('generated_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // Merge snapshot: [{item_id, treatment_name, tooth_numbers[], consent_text, has_consent_text}, ...]
            $table->json('sections');

            $table->timestamps();

            $table->index('treatment_plan_id');
            $table->index('patient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_consents');
    }
};
