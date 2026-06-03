<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Purchase Orders — PO header + line items.
     * Workflow: draft → ordered → partially_received → completed | cancelled
     * GRN (Goods Received Note) is created when items are received, which
     * triggers stock_in movements on the stock_movements ledger.
     */
    public function up(): void
    {
        /* ── PO Header ── */
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 30)->unique();   // e.g. PO-2026-0001
            $table->foreignId('vendor_id')->nullable()->constrained('inventory_vendors')->nullOnDelete();
            $table->date('order_date')->nullable();
            $table->date('expected_date')->nullable();
            $table->enum('status', [
                'draft',
                'ordered',
                'partially_received',
                'completed',
                'cancelled',
            ])->default('draft');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('gst_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('vendor_id');
        });

        /* ── PO Line Items ── */
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty_ordered', 10, 2);
            $table->decimal('qty_received', 10, 2)->default(0);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->string('batch_no', 80)->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('purchase_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
