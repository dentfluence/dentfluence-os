<?php
// =============================================================================
// Vendor Payments — Records each payment made to a vendor.
// =============================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id')->default(1);
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('purchase_order_id')->nullable(); // link to inventory PO if any

            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->enum('payment_mode', ['cash','upi','card','bank_transfer','cheque','other'])->default('bank_transfer');
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('notes')->nullable();

            $table->enum('status', ['active','cancelled','voided'])->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['vendor_id', 'payment_date']);
        });
    }

    public function down(): void { Schema::dropIfExists('finance_vendor_payments'); }
};
