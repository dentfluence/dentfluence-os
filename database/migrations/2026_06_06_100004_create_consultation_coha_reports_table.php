<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2C2d — consultation_coha_reports table.
 *
 * Stores all structured data for a Comprehensive Oral Health Assessment.
 * COHA generates a patient-facing PDF awareness report — not a treatment plan.
 * Each section is a JSON column so the schema stays flexible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_coha_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consultation_id')
                  ->constrained('consultations')
                  ->cascadeOnDelete();

            $table->foreignId('patient_id')
                  ->constrained('patients')
                  ->cascadeOnDelete();

            $table->foreignId('doctor_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // ── Section 1: Extraoral ──
            // {"tmj":"normal","muscles":"normal","lymph_nodes":"not_palpable","facial_symmetry":"symmetric"}
            $table->json('extraoral')->nullable();

            // ── Section 2: Soft tissue ──
            // {"lips":"normal","buccal_mucosa":"normal","tongue":"normal",...,"oral_cancer_screening":"negative"}
            $table->json('soft_tissue')->nullable();

            // ── Section 3: Tooth assessment ──
            // Per-tooth status keyed by FDI number: {"11":"sound","12":"caries","16":"crown",...}
            $table->json('tooth_assessment')->nullable();

            // ── Section 4: Orthodontic findings ──
            $table->json('ortho_findings')->nullable();

            // ── Section 5: Periodontal findings ──
            $table->json('perio_findings')->nullable();

            // ── Section 6: Esthetic findings ──
            $table->json('esthetic_findings')->nullable();

            // ── Section 7: Risk assessment ──
            // {"caries":"high","perio":"medium","bruxism":"low","oral_cancer":"low"}
            $table->json('risk_assessment')->nullable();

            // ── Section 8: Monitoring teeth ──
            // Array of FDI tooth numbers to watch: [16, 26, 36]
            $table->json('monitoring_teeth')->nullable();

            // ── Section 9: Treatment awareness ──
            // What treatments are recommended (in plain language for patient)
            // {"fillings":true,"rct":false,"crowns":true,"ortho":true,...}
            $table->json('treatment_awareness')->nullable();

            // Doctor's narrative notes for the report
            $table->text('doctor_notes')->nullable();

            $table->date('report_date')->nullable();

            // Path to generated PDF (stored after PDF generation)
            $table->string('pdf_path')->nullable();

            $table->timestamps();
        });

        // Now that consultation_coha_reports exists, add the FK on consultations
        Schema::table('consultations', function (Blueprint $table) {
            $table->foreign('coha_report_id')
                  ->references('id')
                  ->on('consultation_coha_reports')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropForeign(['coha_report_id']);
        });

        Schema::dropIfExists('consultation_coha_reports');
    }
};
