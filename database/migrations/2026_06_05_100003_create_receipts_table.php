<?php
// =============================================================================
// F1 — Receipts
// One receipt is issued per payment. A single invoice can have multiple
// receipts (partial payments). Auto-generated when InvoicePayment is saved.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();

            $table->string('receipt_number', 30)->unique(); // RCP-2026-00001
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_payment_id')->constrained('invoice_payments')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->enum('payment_mode', ['cash', 'card', 'upi', 'cheque', 'netbanking', 'emi', 'other'])
                  ->default('cash');
            $table->date('receipt_date');
            $table->string('reference_no', 100)->nullable();

            // Snapshot of invoice balance at time of this payment
            $table->decimal('invoice_total', 12, 2)->default(0);
            $table->decimal('amount_paid_before', 12, 2)->default(0);   // paid before this receipt
            $table->decimal('balance_after', 12, 2)->default(0);        // remaining after this

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['patient_id', 'receipt_date']);
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
