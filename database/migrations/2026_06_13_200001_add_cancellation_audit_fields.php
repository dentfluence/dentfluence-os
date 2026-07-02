<?php
// =============================================================================
// Cancellation & Void Audit Fields
// Adds reason + who + refund details whenever an admin cancels/voids a record.
// Tables affected: invoices, invoice_payments, final_bills
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Invoices: who cancelled it and why ───────────────────────────────
        Schema::table('invoices', function (Blueprint $table) {
            $table->text('cancelled_reason')->nullable()->after('notes');
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_reason');
            // cancelled_at is tracked by deleted_at (softDeletes) + status='cancelled'
        });

        // ── Invoice Payments: void audit + refund details ────────────────────
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->text('void_reason')->nullable()->after('notes');
            $table->unsignedBigInteger('voided_by')->nullable()->after('void_reason');

            // How was the money returned to the patient?
            // wallet   = credited to patient wallet (no physical refund)
            // wallet       = credited to patient wallet (no physical refund)
            // cash         = returned as cash (0% charge)
            // bank_transfer= returned via bank transfer/UPI; charge depends on original mode:
            //                card/debit_card → 2.5% deducted | all others → 0%
            // no_refund    = amount forfeited, nothing returned
            $table->enum('void_refund_method', ['wallet', 'cash', 'bank_transfer', 'no_refund'])
                  ->nullable()
                  ->after('voided_by');

            // Amount the patient actually received (after deduction if applicable)
            $table->decimal('void_refund_amount', 12, 2)
                  ->nullable()
                  ->after('void_refund_method');

            // Clinic charge deducted (2.5% for card/debit_card bank_transfer; 0 otherwise)
            $table->decimal('void_charge_deducted', 10, 2)
                  ->default(0)
                  ->after('void_refund_amount');
        });

        // ── Final Bills: who deleted it and why ──────────────────────────────
        Schema::table('final_bills', function (Blueprint $table) {
            $table->text('deleted_reason')->nullable()->after('notes');
            $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_reason');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['cancelled_reason', 'cancelled_by']);
        });

        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropColumn([
                'void_reason', 'voided_by',
                'void_refund_method', 'void_refund_amount', 'void_charge_deducted',
            ]);
        });

        Schema::table('final_bills', function (Blueprint $table) {
            $table->dropColumn(['deleted_reason', 'deleted_by']);
        });
    }
};
