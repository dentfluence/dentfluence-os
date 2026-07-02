<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab Case Prescriptions — structured clinical prescription per lab case.
 *
 * One row per lab case (hasOne). Stores dynamic clinical fields as JSON
 * so the schema doesn't need altering every time a new treatment type
 * adds a parameter. Key fields (material, shade) are promoted to columns
 * for easy querying and analytics.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_case_prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_case_id')->unique()->constrained('lab_cases')->cascadeOnDelete();

            // Template this was created from (optional)
            $table->foreignId('template_id')->nullable()->constrained('lab_prescription_templates')->nullOnDelete();

            // ── Promoted columns (commonly searched / displayed) ──────────
            $table->string('material', 100)->nullable();        // Zirconia, E-max, PFM …
            $table->string('shade', 30)->nullable();            // A2, B1 …
            $table->string('stump_shade', 30)->nullable();      // underlying tooth shade

            // ── Dynamic clinical fields per treatment category ────────────
            // Stored as JSON; validated/cast by LabCasePrescription model.
            // Keys vary by category — see LabCasePrescription::FIELD_SCHEMA.
            $table->json('clinical_fields')->nullable();

            // ── Smart suggestions ─────────────────────────────────────────
            // Array of suggestion strings auto-generated at creation (e.g.
            // ["Retracted photos", "Shade photo"]). Soft-reminder only.
            $table->json('smart_suggestions')->nullable();
            $table->boolean('suggestions_acknowledged')->default(false);

            // ── Special instructions (free text — final catch-all) ────────
            $table->text('special_instructions')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_case_prescriptions');
    }
};
