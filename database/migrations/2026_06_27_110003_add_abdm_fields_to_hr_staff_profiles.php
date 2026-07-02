<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ABDM Phase 1 · Wave 1 — HPR (Healthcare Professional Registry) identity on staff.
 *
 * A doctor's existing `license_number` (council reg) stays as-is. We add the HPR id
 * and FHIR Practitioner mapping. The digital signature is stored as a REFERENCE to
 * the secret store, never the key material itself.
 *
 * All nullable + additive → safe on live DB, reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_staff_profiles', function (Blueprint $table) {
            $table->string('hpr_id')->nullable()->after('license_number');             // Healthcare Professional Registry id
            $table->string('hpr_verification_status', 20)->nullable()->after('hpr_id'); // unlinked|pending|verified|failed
            $table->timestamp('hpr_linked_at')->nullable()->after('hpr_verification_status');
            $table->string('medical_council_name')->nullable()->after('hpr_linked_at'); // e.g. Maharashtra State Dental Council
            $table->year('registration_year')->nullable()->after('medical_council_name');
            $table->string('digital_signature_ref')->nullable()->after('registration_year'); // pointer to key store — NOT the key
            $table->uuid('fhir_practitioner_id')->nullable()->after('digital_signature_ref');
        });
    }

    public function down(): void
    {
        Schema::table('hr_staff_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'hpr_id', 'hpr_verification_status', 'hpr_linked_at',
                'medical_council_name', 'registration_year',
                'digital_signature_ref', 'fhir_practitioner_id',
            ]);
        });
    }
};
