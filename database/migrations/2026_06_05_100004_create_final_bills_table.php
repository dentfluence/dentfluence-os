<?php
// =============================================================================
// F1 — Final Bills
// Auto-generated when an invoice reaches 100% paid status.
// Acts as the consolidated "paid in full" document the patient takes home.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('final_bills', function (Blueprint $table) {
            $table->id();

            $table->string('bill_number', 30)->unique();    // BILL-2026-00001
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();

            // Snapshot of invoice totals at time of full payment
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('wallet_applied', 12, 2)->default(0);
            $table->decimal('coupon_discount', 12, 2)->default(0);
            $table->decimal('gst_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('total_paid', 12, 2)->default(0);

            $table->date('generated_date');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('invoice_id');
            $table->index(['patient_id', 'generated_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('final_bills');
    }
};
