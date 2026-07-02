<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7F — Documentation Protocols Table
 *
 * A protocol defines what files are required for a given procedure type.
 * e.g. "Root Canal Protocol" requires: Pre-op X-ray (before), Working Length X-ray (during), etc.
 *
 * Run BEFORE clinical_files (300001) and protocol_steps (300003).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentation_protocols', function (Blueprint $table) {

            $table->id();

            // Protocol name, e.g. "Root Canal Protocol", "Implant Protocol"
            $table->string('name');

            // The procedure type this protocol applies to.
            // Free-form string matched against clinical_files.procedure / visit.treatment_name.
            $table->string('procedure_type');

            // Optional description shown in settings UI
            $table->text('description')->nullable();

            // When enabled, this protocol is auto-suggested on new visit creation
            $table->boolean('apply_to_new_visits')->default(true);

            // Soft-disable without deleting
            $table->boolean('is_active')->default(true);

            // Display order in Settings → Clinical Library → Protocols
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Who created this protocol (clinic admin / doctor)
            $table->foreignId('created_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('procedure_type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentation_protocols');
    }
};
