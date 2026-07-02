<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ABDM Phase 1 · Wave 1 — Facility identity on branches.
 *
 * Adds the Health Facility Registry (HFR) + FHIR Organization/Location fields.
 * ALL columns are nullable and additive — nothing existing is touched, so this
 * is 100% safe to run on the live DB and fully reversible (down() drops them).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // Health Facility Registry id (ABDM) — the clinic's national facility id
            $table->string('hfr_id')->nullable()->after('code');
            $table->string('facility_verification_status', 20)->nullable()->after('hfr_id'); // unlinked|pending|verified|failed
            $table->string('facility_type', 40)->nullable()->after('facility_verification_status'); // dental_clinic|clinic|hospital|diagnostic_centre
            $table->string('organization_mapping_id')->nullable()->after('facility_type');

            // Geo-coordinates (HFR registration needs these) → FHIR Location.position
            $table->decimal('geo_lat', 10, 7)->nullable()->after('state');
            $table->decimal('geo_lng', 10, 7)->nullable()->after('geo_lat');

            // FHIR logical ids (filled by the FHIR engine later)
            $table->uuid('fhir_organization_id')->nullable()->after('geo_lng');
            $table->uuid('fhir_location_id')->nullable()->after('fhir_organization_id');

            // Pointer to a digital certificate in the secret store (NEVER the cert itself)
            $table->string('digital_certificate_ref')->nullable()->after('fhir_location_id');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'hfr_id', 'facility_verification_status', 'facility_type',
                'organization_mapping_id', 'geo_lat', 'geo_lng',
                'fhir_organization_id', 'fhir_location_id', 'digital_certificate_ref',
            ]);
        });
    }
};
