<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2C2a — Extend consultations table with new consultation engine fields.
 *
 * Strategy: add new column consultation_type alongside existing visit_type.
 * Old records keep visit_type untouched. New records write both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {

            // ── Consultation type (7-type system, replaces 2-type visit_type) ──
            $table->enum('consultation_type', [
                'new',
                'followup',
                'same_issue',
                'recall_6m',
                'emergency',
                'minor_visit',
                'coha',
            ])->default('new')->after('visit_type');

            // ── HOPI (History of Present Illness) ──
            $table->text('hopi_auto')->nullable()->after('consultation_type');   // system draft
            $table->text('hopi_final')->nullable()->after('hopi_auto');          // doctor-edited

            // ── Findings summary ──
            $table->text('findings_summary_auto')->nullable()->after('hopi_final');
            $table->text('findings_summary_final')->nullable()->after('findings_summary_auto');

            // ── Specialty engine outputs ──
            // JSON: {"orthodontics": {"crowding":"moderate"}, "periodontics": {...}}
            $table->json('specialty_findings')->nullable()->after('findings_summary_final');

            // JSON array: ["orthodontics", "periodontics"]
            $table->json('accepted_specialties')->nullable()->after('specialty_findings');

            // ── Extended diagnosis (add provisional + differential; primary stays as final) ──
            $table->text('provisional_diagnosis')->nullable()->after('accepted_specialties');
            $table->text('differential_diagnosis')->nullable()->after('provisional_diagnosis');
            // primary_diagnosis (final) already exists — no change

            // ── Previous consultation link (for followup / same_issue types) ──
            $table->unsignedBigInteger('previous_consultation_id')->nullable()->after('differential_diagnosis');
            $table->foreign('previous_consultation_id')
                  ->references('id')
                  ->on('consultations')
                  ->nullOnDelete();

            // ── COHA report link ──
            $table->unsignedBigInteger('coha_report_id')->nullable()->after('previous_consultation_id');
            // FK added after consultation_coha_reports table is created (P2C2d)
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropForeign(['previous_consultation_id']);
            $table->dropColumn([
                'consultation_type',
                'hopi_auto',
                'hopi_final',
                'findings_summary_auto',
                'findings_summary_final',
                'specialty_findings',
                'accepted_specialties',
                'provisional_diagnosis',
                'differential_diagnosis',
                'previous_consultation_id',
                'coha_report_id',
            ]);
        });
    }
};
