<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ABDM Phase 1 · Wave 1 — Polymorphic patient identifiers.
 *
 * THE key design decision: a patient is a *bundle of identifiers*, not one number.
 * One row per identifier (internal id, ABHA number, ABHA address, government id,
 * insurance id, FHIR logical id...). This maps 1:1 to FHIR Patient.identifier[]
 * and means we NEVER have to add another "ID column" again.
 *
 * The existing patients.patient_id keeps working; the backfill command (Chunk D)
 * mirrors it in here as type=internal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();

            // internal | abha_number | abha_address | aadhaar_ref | pan |
            // driving_license | passport | insurance | fhir_logical | mrn_external
            $table->string('identifier_type', 30);

            $table->string('system_uri')->nullable();   // FHIR identifier system, e.g. https://healthid.ndhm.gov.in
            $table->text('value')->nullable();           // the value (encrypted at app-level for gov ids)
            $table->string('value_last4', 4)->nullable();// searchable tail when value is encrypted

            $table->string('status', 20)->default('active'); // active|pending|verified|revoked|failed
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('source', 20)->default('manual'); // manual|abdm|import
            $table->json('meta')->nullable();            // e.g. ABHA profile snapshot

            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'identifier_type']);
            $table->index(['identifier_type', 'value_last4']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_identifiers');
    }
};
