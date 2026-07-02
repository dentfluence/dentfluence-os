<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ABDM Phase 1 · Wave 1 — Practitioner qualifications.
 *
 * A doctor can hold multiple degrees/registrations (BDS, MDS, council regs).
 * Maps to FHIR Practitioner.qualification[]. The single hr_staff_profiles.qualification
 * column stays untouched; this is the richer, multi-row version used for FHIR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practitioner_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('degree');                       // e.g. BDS, MDS (Orthodontics)
            $table->string('institution')->nullable();
            $table->year('year')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('council_name')->nullable();

            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practitioner_qualifications');
    }
};
