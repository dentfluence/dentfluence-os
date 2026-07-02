<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ABDM Phase 1 · Wave 1 — Polymorphic practitioner identifiers.
 *
 * Same pattern as patient_identifiers, but for clinicians (users). Types:
 * internal | hpr_id | council_reg | fhir_logical. Maps to FHIR
 * Practitioner.identifier[]. Existing license_number keeps working; backfill
 * mirrors it as type=council_reg and the user id as type=internal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practitioner_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('identifier_type', 30); // internal|hpr_id|council_reg|fhir_logical
            $table->string('system_uri')->nullable();
            $table->text('value')->nullable();
            $table->string('value_last4', 4)->nullable();

            $table->string('status', 20)->default('active');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('source', 20)->default('manual');
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'identifier_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practitioner_identifiers');
    }
};
