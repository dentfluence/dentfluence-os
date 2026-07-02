<?php
// =============================================================================
// Finance Transactions — Master ledger. Every money movement lands here.
// Linked to: income entries, expense entries, payments, payroll, purchases.
// =============================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id')->default(1);   // multi-clinic ready

            // Type & direction
            $table->enum('type', [
                'income', 'expense', 'payroll', 'purchase',
                'transfer', 'refund', 'advance', 'adjustment'
            ]);
            $table->enum('direction', ['credit', 'debit']);

            // Reference to source record (polymorphic-style)
            $table->string('source_type')->nullable();  // App\Models\Finance\IncomeEntry etc.
            $table->unsignedBigInteger('source_id')->nullable();

            // Core financial data
            $table->decimal('amount', 12, 2);
            $table->decimal('gst_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);           // amount - discount + gst

            // Payment
            $table->enum('payment_mode', [
                'cash', 'upi', 'card', 'bank_transfer',
                'cheque', 'emi', 'insurance', 'wallet', 'other'
            ])->default('cash');
            $table->string('payment_reference')->nullable(); // UPI txn id / cheque no
            $table->unsignedBigInteger('bank_account_id')->nullable();

            // Status
            $table->enum('status', ['active', 'cancelled', 'voided', 'pending'])->default('active');

            // Links
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();        // staff who recorded
            $table->unsignedBigInteger('vendor_id')->nullable();

            // GST fields (gated by settings toggle)
            $table->boolean('gst_applicable')->default(false);
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('cgst', 10, 2)->default(0);
            $table->decimal('sgst', 10, 2)->default(0);
            $table->decimal('igst', 10, 2)->default(0);
            $table->string('hsn_sac')->nullable();

            // Dates
            $table->date('transaction_date');
            $table->string('notes')->nullable();

            // Audit trail
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('updated_reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('device_info')->nullable();

            $table->softDeletes(); // no hard deletes
            $table->timestamps();

            $table->index(['clinic_id', 'transaction_date']);
            $table->index(['type', 'status']);
            $table->index(['patient_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('finance_transactions'); }
};
