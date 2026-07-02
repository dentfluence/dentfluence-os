<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ABDM Phase 2 · Slice 1 — terminology maps (FHIR ConceptMap).
 *
 * Maps a LOCAL term/code (how Dentfluence stores it) to a STANDARD code
 * (SNOMED CT / LOINC / ICD-10 / WHO-ATC / FDI) that FHIR + ABDM require.
 * Keeping this as DATA (not hard-coded) is the single biggest 15-year decision
 * in the mapping layer: new codes are inserted rows, never code deploys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminology_maps', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 30);          // tooth | gender | condition | procedure | observation | drug | document_type
            $table->string('local_code')->nullable();
            $table->string('local_term')->nullable();
            $table->string('standard_system');     // e.g. http://snomed.info/sct, urn:iso:std:iso:3950
            $table->string('standard_code');
            $table->string('standard_display')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['domain', 'local_code']);
            $table->index(['domain', 'local_term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminology_maps');
    }
};
