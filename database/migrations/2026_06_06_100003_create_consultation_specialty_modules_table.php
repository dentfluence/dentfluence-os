<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2C2c — consultation_specialty_modules table.
 *
 * Tracks which specialty modules were activated for a consultation
 * and stores the structured findings entered in each module.
 *
 * One row per specialty per consultation.
 * e.g. consultation 42 with orthodontics + periodontics = 2 rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop partial table from a failed previous run (identifier-too-long error)
        Schema::dropIfExists('consultation_specialty_modules');

        Schema::create('consultation_specialty_modules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consultation_id')
                  ->constrained('consultations')
                  ->cascadeOnDelete();

            // Matches treatment_knowledge.specialty_tag
            $table->string('specialty_tag', 50);

            // Structured findings entered by the doctor for this module.
            // JSON object keyed by field name:
            // {"ortho_crowding":"moderate","ortho_overjet":"increased",...}
            $table->json('findings')->nullable();

            // When the doctor accepted / rejected this module suggestion
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();

            // A consultation can have each specialty module only once
            // Custom short name — MySQL has a 64-char limit on identifiers
            $table->unique(['consultation_id', 'specialty_tag'], 'csm_consultation_specialty_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_specialty_modules');
    }
};
