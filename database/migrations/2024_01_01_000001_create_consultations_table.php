<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();

            // Core relationships
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id'); // no FK — branches table doesn't exist yet

            // Status & date
            $table->enum('status', ['draft', 'completed'])->default('draft');
            $table->timestamp('consultation_date')->useCurrent();

            // Section 1 — Chief Complaint
            $table->text('chief_complaint')->nullable();
            $table->string('complaint_duration')->nullable();
            $table->string('severity')->nullable();
            $table->string('tooth_area')->nullable();
            $table->string('location')->nullable();
            $table->text('complaint_notes')->nullable();

            // Section 2 — Visit Type
            $table->enum('visit_type', ['emergency', 'routine', 'followup'])->nullable();

            // Section 3 — Photographs
            $table->json('photographs')->nullable(); // keyed by slot name

            // Section 4 — Scans
            $table->date('scan_date')->nullable();
            $table->json('scan_files')->nullable();

            // Section 5 — Investigations
            $table->json('investigations')->nullable();
            $table->json('investigation_details')->nullable();

            // Section 6 — Clinical Data & Chart
            $table->json('clinical_data')->nullable();
            // keys: soft_tissue, caries, periodontal, bleeding_on_probing,
            //       plaque_index, occlusion, tmj, existing_condition,
            //       oral_hygiene, notes
            $table->json('chart_data')->nullable();

            // Section 7 — Radiography
            $table->json('radio_data')->nullable(); // keys: opg, iopa, cbct, notes

            // Section 8 — DBM / Digital Smile Design
            $table->json('dbm_checklist')->nullable();
            $table->unsignedTinyInteger('dbm_score')->nullable();
            $table->string('dbm_tooth_shade')->nullable();
            $table->string('dbm_whitening')->nullable();
            $table->string('dbm_tooth_monitored')->nullable();

            // Rx
            $table->json('prescriptions')->nullable();
            $table->json('instructions')->nullable();

            // Section 9 — Diagnosis
            $table->text('primary_diagnosis')->nullable();
            $table->text('secondary_diagnosis')->nullable();
            $table->string('risk_assessment')->nullable();
            $table->text('diagnosis_notes')->nullable();

            // Section 10 — Treatment Options
            $table->json('tx_emergency')->nullable();
            $table->json('tx_protective')->nullable();
            $table->json('tx_transformative')->nullable();
            $table->json('tx_teeth')->nullable();

            // Section 11 — Treatment Plans
            $table->json('treatment_plan_best')->nullable();
            $table->decimal('treatment_plan_best_total', 10, 2)->nullable();
            $table->json('treatment_plan_acceptable')->nullable();
            $table->decimal('treatment_plan_acc_total', 10, 2)->nullable();
            $table->boolean('aocp_best')->default(false);
            $table->string('aocp_best_plan')->nullable();
            $table->boolean('aocp_acceptable')->default(false);
            $table->string('aocp_acceptable_plan')->nullable();

            // Section 12 — Finishing / Follow-up
            $table->text('finishing_notes')->nullable();
            $table->string('next_visit_type')->nullable();
            $table->date('next_visit_date')->nullable();
            $table->string('recall_interval')->nullable();
            $table->string('recall_custom')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('attachments')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
