<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2C2b — treatment_knowledge table.
 *
 * The rules engine brain. Each row = one specialty.
 * Stores keyword triggers, suggested questions/investigations/diagnoses,
 * and the full field config for the dynamic specialty module panel.
 *
 * Adding a new specialty (TMJ, Pediatric, Sleep) = adding one row here.
 * Zero consultation code changes required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_knowledge', function (Blueprint $table) {
            $table->id();

            // Unique slug — matched against in code: 'orthodontics', 'periodontics' etc.
            $table->string('specialty_tag', 50)->unique();

            // Human-readable label shown in Consult Assist panel
            $table->string('display_label', 100);

            // Icon name (Tabler icon slug, e.g. 'tooth', 'heart')
            $table->string('display_icon', 50)->nullable();

            // Keywords in chief complaint that activate this specialty
            // JSON array: ["braces","aligners","crooked","crowding"]
            $table->json('trigger_keywords');

            // What kind of concern is this? JSON array of concern labels
            // ["cosmetic","functional","pain","preventive"]
            $table->json('patient_concerns')->nullable();

            // Questions to surface in Consult Assist panel
            // JSON array of strings
            $table->json('suggested_questions')->nullable();

            // Clinical findings to look for (for auto-HOPI draft)
            // JSON array of finding labels
            $table->json('suggested_findings')->nullable();

            // Investigations to recommend
            // JSON array: ["IOPA","OPG","CBCT","Photos"]
            $table->json('suggested_investigations')->nullable();

            // Possible diagnoses to suggest in the Diagnosis section
            // JSON array of diagnosis strings
            $table->json('possible_diagnoses')->nullable();

            // Full field config for the dynamic specialty module panel.
            // JSON array of field definitions:
            // [{"label":"Crowding","name":"ortho_crowding","options":["None","Mild","Moderate","Severe"]}, ...]
            $table->json('module_config')->nullable();

            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_knowledge');
    }
};
