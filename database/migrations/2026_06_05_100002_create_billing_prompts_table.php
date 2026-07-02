<?php
// =============================================================================
// F1 — Billing Prompts
// Auto-generated notification for front desk when doctor completes a visit
// or consultation. Front desk sees this in the patient profile and uses it
// to build an invoice.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_prompts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')
                  ->constrained('patients')
                  ->cascadeOnDelete();

            // What triggered this prompt
            $table->enum('trigger_type', [
                'consultation',         // new consultation recorded
                'treatment_visit',      // doctor completed/started a visit
                'membership_offer',     // system suggests AOCP at billing time
                'manual',               // manually added by staff
            ]);

            // Reference to the triggering record (nullable for manual)
            $table->unsignedBigInteger('trigger_id')->nullable();   // consultation_id or visit_id

            // Human-readable description shown to front desk
            // e.g. "Bill for: RCT (Ceramic Crown) — Tooth 26"
            $table->string('description', 300);

            // Prompt lifecycle
            $table->enum('status', ['pending', 'invoiced', 'dismissed'])->default('pending');

            // Which invoice was created from this prompt (set after invoice is created)
            $table->foreignId('invoice_id')
                  ->nullable()
                  ->constrained('invoices')
                  ->nullOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();   // usually system (null) or doctor
            $table->unsignedBigInteger('resolved_by')->nullable();  // front desk user

            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index(['trigger_type', 'trigger_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_prompts');
    }
};
