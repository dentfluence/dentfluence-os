<?php
// =============================================================================
// Phase 1 — Vendor Invoice Management
//
// vendor_invoices      — invoice header (multiple per PO allowed)
// vendor_invoice_items — line items per invoice
//
// On save, VendorInvoiceController auto-creates:
//   1. FinanceExpense (Accounts Payable / unpaid bill)
//   2. Updates PO invoice_status + invoiced_amount
//
// Finance remains the single source of truth for payments.
// Invoices here are the procurement record; payment happens in Finance.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ── Vendor Invoice Header ── */
        Schema::create('vendor_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_ref', 30)->unique();            // VI-2026-0001

            // PO linkage — required (invoices must trace to a PO)
            $table->foreignId('purchase_order_id')
                  ->constrained('purchase_orders')
                  ->cascadeOnDelete();

            // Vendor linkage — Finance vendor is authoritative for payments
            $table->foreignId('finance_vendor_id')
                  ->nullable()
                  ->constrained('finance_vendors')
                  ->nullOnDelete();

            // Inventory vendor (convenience FK, same as PO.vendor_id)
            $table->foreignId('inventory_vendor_id')
                  ->nullable()
                  ->constrained('inventory_vendors')
                  ->nullOnDelete();

            // Invoice details
            $table->string('invoice_number')->nullable();           // vendor's own invoice number
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('payment_terms')->nullable();            // e.g. "Net 30", "Due on receipt"

            // Amounts
            $table->decimal('invoice_amount', 12, 2);               // subtotal before GST
            $table->decimal('gst_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 12, 2);                 // invoice_amount + gst_amount

            // Bill attachment (file path or URL)
            $table->string('bill_attachment')->nullable();

            // Notes
            $table->text('notes')->nullable();

            // Workflow
            $table->enum('status', ['draft', 'pending', 'approved', 'paid', 'cancelled'])
                  ->default('pending');

            // Link to the auto-created FinanceExpense (AP entry)
            $table->foreignId('finance_expense_id')
                  ->nullable()
                  ->constrained('finance_expenses')
                  ->nullOnDelete()
                  ->comment('Auto-created Accounts Payable entry in Finance');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['purchase_order_id']);
            $table->index(['finance_vendor_id']);
            $table->index(['invoice_date']);
            $table->index(['due_date']);
            $table->index(['status']);
        });

        /* ── Vendor Invoice Line Items ── */
        Schema::create('vendor_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_invoice_id')
                  ->constrained('vendor_invoices')
                  ->cascadeOnDelete();
            $table->foreignId('inventory_item_id')
                  ->nullable()
                  ->constrained('inventory_items')
                  ->nullOnDelete();

            $table->string('description')->nullable();              // free-text if item not linked
            $table->decimal('qty', 10, 2)->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);

            $table->timestamps();

            $table->index(['vendor_invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_invoice_items');
        Schema::dropIfExists('vendor_invoices');
    }
};
