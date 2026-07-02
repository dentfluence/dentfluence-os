<?php
// =============================================================================
// Phase 1 — GRN Enhancements
//
// Creates:
//   goods_receipt_notes — GRN header; multiple per PO for partial deliveries.
//   grn_items           — GRN line items; each has batch, expiry, unit price.
//
// The existing StockMovement ledger is NOT replaced — each GRN item continues
// to create a StockMovement (stock_in). The GRN tables add traceability:
// you can view all GRNs for a PO, see pending quantities, and link invoices
// directly to GRNs in Phase 2.
//
// grn_number format: GRN-2026-0001
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ── GRN Header ── */
        Schema::create('goods_receipt_notes', function (Blueprint $table) {
            $table->id();
            $table->string('grn_number', 30)->unique();             // GRN-2026-0001
            $table->foreignId('purchase_order_id')
                  ->constrained('purchase_orders')
                  ->cascadeOnDelete();
            $table->foreignId('vendor_id')
                  ->nullable()
                  ->constrained('inventory_vendors')
                  ->nullOnDelete();

            $table->date('received_date');
            $table->unsignedBigInteger('location_id')->nullable();  // inventory_locations FK (nullable for portability)
            $table->text('notes')->nullable();

            // Invoice linkage (filled when GRN is matched to a vendor invoice in Phase 1/2)
            $table->foreignId('vendor_invoice_id')
                  ->nullable()
                  ->comment('Set when this GRN is matched to a Vendor Invoice');

            $table->enum('status', ['draft', 'confirmed', 'invoiced'])->default('confirmed');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['purchase_order_id']);
            $table->index(['received_date']);
        });

        /* ── GRN Line Items ── */
        Schema::create('grn_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grn_id')
                  ->constrained('goods_receipt_notes')
                  ->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')
                  ->nullable()
                  ->constrained('purchase_order_items')
                  ->nullOnDelete();
            $table->foreignId('inventory_item_id')
                  ->constrained('inventory_items')
                  ->cascadeOnDelete();

            $table->decimal('qty_received', 10, 2);
            $table->decimal('unit_price', 10, 2)->default(0);       // price at receipt (may differ from PO price)
            $table->decimal('total_price', 10, 2)->default(0);      // qty_received * unit_price

            $table->string('batch_no', 80)->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();

            // Link to the StockMovement record created for this GRN item
            $table->unsignedBigInteger('stock_movement_id')->nullable();

            $table->timestamps();

            $table->index(['grn_id']);
            $table->index(['inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_items');
        Schema::dropIfExists('goods_receipt_notes');
    }
};
