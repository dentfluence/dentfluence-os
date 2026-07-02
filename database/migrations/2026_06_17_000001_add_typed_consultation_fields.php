<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consultation Module Refactor — add dedicated columns for
 * Same Issue, Minor Visit, and Emergency consultation types.
 *
 * All new columns are nullable so existing records are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {

            // ── Same Issue ────────────────────────────────────────────────────
            // Patient's current update (why they returned, family decision, etc.)
            $table->text('update_notes')->nullable()->after('finishing_notes');

            // Any new clinical findings observed today (different from original exam)
            $table->text('additional_findings')->nullable()->after('update_notes');

            // ── Minor Visit ───────────────────────────────────────────────────
            // Was this minor visit related to treatment performed at this clinic?
            // Drives whether full medico-legal docs are needed.
            $table->boolean('related_to_clinic_treatment')->nullable()->after('additional_findings');

            // Procedure carried out today (suture removal, recementation, etc.)
            $table->text('procedure_performed')->nullable()->after('related_to_clinic_treatment');

            // Advice given to patient post-procedure (shared: minor + emergency)
            $table->text('advice')->nullable()->after('procedure_performed');

            // ── Emergency ─────────────────────────────────────────────────────
            // What was done during the emergency visit (stabilisation, dressing, etc.)
            $table->text('emergency_treatment_rendered')->nullable()->after('advice');

            // If this emergency visit was later converted to a New Consultation,
            // store the ID of that new consultation for linking on the timeline.
            $table->unsignedBigInteger('converted_to_consultation_id')->nullable()->after('emergency_treatment_rendered');
            $table->foreign('converted_to_consultation_id')
                  ->references('id')
                  ->on('consultations')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropForeign(['converted_to_consultation_id']);
            $table->dropColumn([
                'update_notes',
                'additional_findings',
                'related_to_clinic_treatment',
                'procedure_performed',
                'advice',
                'emergency_treatment_rendered',
                'converted_to_consultation_id',
            ]);
        });
    }
};
