<?php
// =============================================================================
// Phase 1 — PO Module Enhancements
//
// Adds to purchase_orders:
//   finance_vendor_id  — direct link to Finance vendor (bypasses name-matching heuristic)
//   approved_by        — who approved the PO
//   approved_at        — when approved
//   invoice_status     — tracks invoicing progress (none / partial / fully_invoiced)
//   invoiced_amount    — cumulative amount of vendor invoices raised against this PO
//
// The existing status enum (draft→ordered→partially_received→completed|cancelled)
// is NOT changed. invoice_status is a separate dimension.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Direct Finance vendor link — more reliable than the name-match heuristic
            $table->foreignId('finance_vendor_id')
                  ->nullable()
                  ->after('vendor_id')
                  ->constrained('finance_vendors')
                  ->nullOnDelete();

            // Approval workflow fields
            $table->foreignId('approved_by')
                  ->nullable()
                  ->after('created_by')
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('approved_at')->nullable()->after('approved_by');

            // Invoice tracking (updated by VendorInvoice observer)
            $table->enum('invoice_status', ['none', 'partial', 'fully_invoiced'])
                  ->default('none')
                  ->after('status');

            $table->decimal('invoiced_amount', 12, 2)
                  ->default(0)
                  ->after('invoice_status');

            $table->index('invoice_status');
            $table->index('finance_vendor_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['finance_vendor_id']);
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['invoice_status']);
            $table->dropIndex(['finance_vendor_id']);
            $table->dropColumn([
                'finance_vendor_id', 'approved_by', 'approved_at',
                'invoice_status', 'invoiced_amount',
            ]);
        });
    }
};
