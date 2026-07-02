<?php
// =============================================================================
// Phase 2 — Finance Vouchers
//
// A voucher is generated whenever an expense is marked as paid.
// It provides a formal payment document with print + PDF support.
//
// Voucher is permanently linked to the expense (no cascade delete).
// Expense soft-delete does NOT delete the voucher (audit trail).
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_vouchers', function (Blueprint $table) {
            $table->id();

            // Auto-generated: VCH-2026-0001
            $table->string('voucher_number', 30)->unique();

            // Linked expense (required — vouchers always trace to an expense)
            $table->foreignId('expense_id')
                  ->constrained('finance_expenses')
                  ->cascadeOnDelete();

            // Vendor (denormalized from expense at time of voucher creation for permanence)
            $table->foreignId('vendor_id')
                  ->nullable()
                  ->constrained('finance_vendors')
                  ->nullOnDelete();

            $table->string('vendor_name', 150)->nullable();  // snapshot at creation time

            // Payment details (snapshot from expense.paid_* fields)
            $table->date('voucher_date');
            $table->decimal('amount', 12, 2);
            $table->string('payment_mode', 30)->nullable();  // cash|upi|card|bank_transfer|cheque|other
            $table->string('reference', 100)->nullable();    // UTR / cheque number / transaction ID

            // Optional metadata
            $table->text('notes')->nullable();
            $table->string('purpose', 200)->nullable();      // what the payment was for (from expense title)

            // Approval workflow (simple — no blocking, just audit)
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->foreignId('approved_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            // Source polymorphic — so vouchers can be linked to non-expense sources in future
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->timestamps();
            // No softDeletes — vouchers are permanent financial documents

            $table->index(['expense_id']);
            $table->index(['vendor_id']);
            $table->index(['voucher_date']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_vouchers');
    }
};
