<?php
// =============================================================================
// Income Entries — Captures every rupee coming into the clinic.
// Auto-populated from Billing. Also supports manual entries.
// =============================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_income_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id')->default(1);
            $table->unsignedBigInteger('transaction_id')->nullable(); // links to master ledger

            // Source
            $table->enum('source', [
                'patient_billing', 'treatment_payment', 'advance',
                'membership', 'product_sale', 'lab_recovery',
                'consultation', 'package', 'insurance', 'corporate', 'manual'
            ]);
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('bill_id')->nullable();        // from billing module
            $table->unsignedBigInteger('treatment_id')->nullable();

            // Category
            $table->enum('category', [
                'consultation', 'rct', 'implant', 'aligners', 'crown',
                'extraction', 'membership', 'whitening', 'surgery',
                'scaling', 'xray', 'product', 'other'
            ])->default('other');

            // Amounts
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->decimal('advance_adjusted', 12, 2)->default(0);
            $table->decimal('outstanding', 12, 2)->default(0);

            // Split payment support
            $table->json('payment_splits')->nullable(); // [{mode:'cash',amount:500},{mode:'upi',amount:1000}]

            $table->date('income_date');
            $table->string('doctor_name')->nullable();
            $table->string('notes')->nullable();
            $table->enum('status', ['active', 'refunded', 'partially_refunded', 'cancelled'])->default('active');

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['clinic_id', 'income_date']);
            $table->index(['patient_id']);
            $table->index(['category']);
        });
    }

    public function down(): void { Schema::dropIfExists('finance_income_entries'); }
};
