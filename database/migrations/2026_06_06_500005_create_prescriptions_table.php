<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prescriptions — core prescription header + items.
 * Every prescription MUST link to a visit_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('prescription_number')->unique();  // RX-2026-00001

            // ── Links ─────────────────────────────────────────────────────────
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained('treatment_visits')->nullOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();
            $table->foreignId('prescribed_by')->constrained('users')->restrictOnDelete();

            // ── Clinical context ──────────────────────────────────────────────
            $table->string('diagnosis')->nullable();
            $table->string('chief_complaint')->nullable();
            $table->string('follow_up_date')->nullable();
            $table->text('general_instructions')->nullable();
            $table->string('language')->default('en');  // en, mr, hi

            // ── Status ────────────────────────────────────────────────────────
            $table->enum('status', ['draft', 'finalized', 'cancelled'])->default('draft');

            // ── Output tracking ───────────────────────────────────────────────
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->integer('print_count')->default(0);

            // ── Template source ───────────────────────────────────────────────
            $table->foreignId('template_id')->nullable()->constrained('rx_templates')->nullOnDelete();
            $table->foreignId('repeated_from_id')->nullable()->constrained('prescriptions')->nullOnDelete();

            // ── Versioning (no deletion after finalize) ───────────────────────
            $table->integer('version')->default(1);
            $table->foreignId('parent_id')->nullable()->constrained('prescriptions')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes(); // soft-only; finalized prescriptions never hard-deleted

            $table->index('patient_id');
            $table->index('visit_id');
            $table->index('status');
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->foreignId('drug_id')->nullable()->constrained('rx_drugs')->nullOnDelete();

            // ── Drug details (snapshot at time of Rx, drug master may change) ──
            $table->string('drug_name');          // snapshot of brand name
            $table->string('generic_name')->nullable();
            $table->string('strength')->nullable();
            $table->string('dosage_form')->nullable();
            $table->string('route')->nullable();

            // ── Dosing ────────────────────────────────────────────────────────
            $table->decimal('morning', 4, 2)->default(0);
            $table->decimal('afternoon', 4, 2)->default(0);
            $table->decimal('night', 4, 2)->default(0);
            $table->boolean('is_sos')->default(false);
            $table->integer('duration')->nullable();
            $table->enum('duration_unit', ['days', 'weeks', 'months'])->default('days');
            $table->integer('quantity')->nullable();       // auto-calculated or manual
            $table->boolean('quantity_manual')->default(false);

            // ── Instructions ──────────────────────────────────────────────────
            $table->string('food_advice')->nullable();
            $table->text('instructions')->nullable();
            $table->text('patient_instruction_en')->nullable();
            $table->text('patient_instruction_mr')->nullable();
            $table->text('patient_instruction_hi')->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ── Prescription Audit Log ────────────────────────────────────────────
        Schema::create('prescription_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('action', ['created','edited','finalized','printed','whatsapp_sent','repeated','cancelled','override']);
            $table->text('notes')->nullable();   // override reason stored here
            $table->json('snapshot')->nullable(); // JSON snapshot of prescription at action time
            $table->timestamps();
        });

        // ── CDSS Override Log ─────────────────────────────────────────────────
        Schema::create('prescription_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('drug_id')->nullable()->constrained('rx_drugs')->nullOnDelete();
            $table->string('alert_type');  // allergy, interaction, warning, duplicate
            $table->string('alert_code')->nullable();
            $table->text('alert_message');
            $table->text('override_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_overrides');
        Schema::dropIfExists('prescription_audit_logs');
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
    }
};
