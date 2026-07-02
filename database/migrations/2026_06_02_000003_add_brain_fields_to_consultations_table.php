<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Dentfluence Brain / AI-first consultation fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            if (!Schema::hasColumn('consultations', 'raw_note')) {
                $table->longText('raw_note')->nullable()
                    ->comment('The doctor\'s unstructured natural-language note — source of truth for AI extraction');
            }
            if (!Schema::hasColumn('consultations', 'tooth_numbers')) {
                $table->json('tooth_numbers')->nullable()
                    ->comment('Array of FDI tooth numbers extracted from note, e.g. ["46","47"]');
            }
            if (!Schema::hasColumn('consultations', 'treatment_done')) {
                $table->text('treatment_done')->nullable()
                    ->comment('Procedures performed in this visit');
            }
            if (!Schema::hasColumn('consultations', 'treatment_plan_note')) {
                $table->text('treatment_plan_note')->nullable()
                    ->comment('Treatment advice / next steps recommended');
            }
            if (!Schema::hasColumn('consultations', 'follow_up_note')) {
                $table->string('follow_up_note', 300)->nullable()
                    ->comment('Free text follow-up instruction, e.g. Review after 7 days');
            }
            if (!Schema::hasColumn('consultations', 'follow_up_date')) {
                $table->date('follow_up_date')->nullable()
                    ->comment('Calculated follow-up date from follow_up_note');
            }
            if (!Schema::hasColumn('consultations', 'risks_discussed')) {
                $table->string('risks_discussed', 500)->nullable()
                    ->comment('Risks / complications discussed with patient');
            }
            if (!Schema::hasColumn('consultations', 'treatment_acceptance')) {
                $table->enum('treatment_acceptance', ['accepted', 'pending', 'refused', 'deferred'])
                    ->default('pending')->nullable();
            }
            if (!Schema::hasColumn('consultations', 'prescription_notes')) {
                $table->text('prescription_notes')->nullable();
            }
            if (!Schema::hasColumn('consultations', 'examination_notes')) {
                $table->text('examination_notes')->nullable()
                    ->comment('Clinical examination findings');
            }
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $cols = [
                'raw_note', 'tooth_numbers', 'treatment_done', 'treatment_plan_note',
                'follow_up_note', 'follow_up_date', 'risks_discussed',
                'treatment_acceptance', 'prescription_notes', 'examination_notes',
            ];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('consultations', $c));
            if ($existing) $table->dropColumn(array_values($existing));
        });
    }
};
