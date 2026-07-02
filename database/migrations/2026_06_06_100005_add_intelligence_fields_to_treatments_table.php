<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2C2e — Add intelligence fields to treatments table.
 *
 * Turns each Treatment record into a knowledge base entry.
 * The consultation rules engine reads these fields to suggest
 * specialty modules, questions, investigations, and diagnoses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatments', function (Blueprint $table) {

            // Keywords that, if found in chief complaint, link to this treatment's specialty
            // JSON array: ["braces","aligners","crowding","spacing"]
            $table->json('trigger_keywords')->nullable()->after('description');

            // Type of patient concern this treatment addresses
            // JSON array: ["cosmetic","functional","pain","preventive"]
            $table->json('patient_concerns')->nullable()->after('trigger_keywords');

            // Questions to suggest in the Consult Assist panel
            // JSON array of strings
            $table->json('suggested_questions')->nullable()->after('patient_concerns');

            // Clinical findings relevant to this treatment
            // JSON array of finding labels
            $table->json('suggested_findings')->nullable()->after('suggested_questions');

            // Investigations to recommend before this treatment
            // JSON array: ["IOPA","OPG","CBCT","Photos","Study models"]
            $table->json('suggested_investigations')->nullable()->after('suggested_findings');

            // Diagnoses that typically lead to this treatment
            // JSON array of diagnosis strings
            $table->json('possible_diagnoses')->nullable()->after('suggested_investigations');

            // Links this treatment to a specialty tag in treatment_knowledge
            // e.g. 'orthodontics', 'periodontics', 'endodontics'
            $table->string('specialty_tag', 50)->nullable()->after('possible_diagnoses');

            // For future use
            $table->text('consent_template')->nullable()->after('specialty_tag');
            $table->text('patient_instructions')->nullable()->after('consent_template');
            $table->json('treatment_pathways')->nullable()->after('patient_instructions');
        });
    }

    public function down(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->dropColumn([
                'trigger_keywords',
                'patient_concerns',
                'suggested_questions',
                'suggested_findings',
                'suggested_investigations',
                'possible_diagnoses',
                'specialty_tag',
                'consent_template',
                'patient_instructions',
                'treatment_pathways',
            ]);
        });
    }
};
