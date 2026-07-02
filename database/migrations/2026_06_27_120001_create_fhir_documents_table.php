<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ABDM Phase 2 · Slice 1 — generated FHIR documents.
 *
 * Every FHIR resource/Bundle the mapping engine produces is recorded here:
 * what it is, which internal record owns it (polymorphic), a stable FHIR id,
 * a version, a content hash (tamper-evidence + change-detection), and the
 * content itself (stored inline for now; later large bundles move to object
 * storage via content_ref). This is the single place all generated FHIR lives,
 * which is what makes ABDM certification a one-surface job. Additive + new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fhir_documents', function (Blueprint $table) {
            $table->id();

            // Which internal record this FHIR resource was generated from (polymorphic):
            // e.g. owner_type=App\Models\Patient, owner_id=42
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');

            $table->string('resource_type', 60);          // Patient | Encounter | MedicationRequest | Bundle ...
            $table->uuid('fhir_id');                        // stable FHIR logical id
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('draft');// draft | final | amended
            $table->string('bundle_type', 40)->nullable(); // e.g. op_consultation, prescription (null for single resources)

            $table->longText('content')->nullable();        // the FHIR JSON (inline for now)
            $table->string('content_ref')->nullable();      // object-store path (future, for large bundles)
            $table->string('content_hash', 64)->nullable(); // sha256 of content

            $table->boolean('signed')->default(false);
            $table->string('signature_ref')->nullable();    // pointer to signature in secret store

            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
            $table->index(['resource_type', 'status']);
            $table->index('fhir_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fhir_documents');
    }
};
