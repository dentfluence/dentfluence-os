<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ABDM Phase 2 · Slice 4 — first-class patient allergies.
 *
 * Today allergies live as a JSON array on patients.allergies. That's fine for the
 * CDSS but can't become a FHIR AllergyIntolerance resource or be coded. This table
 * promotes them to queryable rows WITHOUT removing the JSON column (kept mirrored,
 * non-breaking). The FHIR AllergyBuilder reads these rows, falling back to the JSON
 * when a patient has none yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();

            $table->string('substance');                 // free text or coded label
            $table->string('snomed_code')->nullable();   // coding for FHIR (optional)
            $table->string('category', 20)->default('medication'); // medication|food|environment|biologic
            $table->string('criticality', 20)->nullable();         // low|high|unable-to-assess
            $table->string('reaction')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('intake'); // intake|clinician|abdm_external

            $table->timestamps();
            $table->softDeletes();

            $table->index('patient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_allergies');
    }
};
