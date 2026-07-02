<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ABDM Phase 1 · Wave 1 — ABHA identity on patients.
 *
 * These are fast-lookup MIRROR columns for the most-used identifiers. The full,
 * multi-identifier source of truth lives in the new `patient_identifiers` table
 * (so we never run another "add an ID column" migration). The existing
 * `patients.patient_id` and `patients.allergies` columns are left untouched.
 *
 * All nullable + additive → safe on live DB, reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // ABHA = Ayushman Bharat Health Account (the citizen's portable health id)
            $table->string('abha_number', 17)->nullable()->after('patient_id');   // 14 digits, stored with hyphens
            $table->string('abha_address')->nullable()->after('abha_number');       // e.g. name@sbx
            $table->string('abha_verification_status', 20)->nullable()->default('unlinked')->after('abha_address'); // unlinked|pending|verified|failed|revoked
            $table->timestamp('abha_linked_at')->nullable()->after('abha_verification_status');

            // Drives FHIR Patient.communication + which language we print Rx/notes in
            $table->string('preferred_language', 8)->nullable()->after('abha_linked_at'); // en|hi|mr

            // Stable FHIR logical id for this patient (filled by FHIR engine)
            $table->uuid('fhir_resource_id')->nullable()->after('preferred_language');

            // Government id mapping — we store TYPE + last 4 only here; any full value
            // (rare) is encrypted inside patient_identifiers. Never store full Aadhaar.
            $table->string('gov_id_type', 30)->nullable()->after('fhir_resource_id');
            $table->string('gov_id_last4', 4)->nullable()->after('gov_id_type');

            // Denormalized count of ABDM care-contexts (linked visits) for quick display
            $table->unsignedInteger('abdm_care_contexts_count')->default(0)->after('gov_id_last4');

            $table->index('abha_number');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['abha_number']);
            $table->dropColumn([
                'abha_number', 'abha_address', 'abha_verification_status', 'abha_linked_at',
                'preferred_language', 'fhir_resource_id', 'gov_id_type', 'gov_id_last4',
                'abdm_care_contexts_count',
            ]);
        });
    }
};
