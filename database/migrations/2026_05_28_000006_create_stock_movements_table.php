<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock Movements — THE CORE LEDGER. Every inventory change is an event here.
     *
     * Philosophy (like a banking system):
     * - stock_in    → receiving goods (GRN, opening stock)
     * - stock_out   → dispensing / consuming
     * - transfer    → moving between locations (from_location → to_location)
     * - adjustment  → manual correction with reason
     * - expired     → writing off expired batch
     * - damaged     → damaged / broken write-off
     * - treatment_usage → auto deduction linked to a completed treatment (future)
     * - sterilization   → reusable instrument sent to sterilization
     * - maintenance     → reusable asset sent to maintenance
     *
     * Positive qty = goods entering system.
     * Negative qty = goods leaving system.
     * The engine that updates inventory_stocks sits in StockMovement::boot() observer.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();

            $table->enum('movement_type', [
                'stock_in',
                'stock_out',
                'transfer',
                'adjustment',
                'expired',
                'damaged',
                'treatment_usage',
                'sterilization',
                'maintenance',
                'opening_stock',
            ]);

            $table->decimal('qty', 10, 2);                             // positive = in, negative = out
            $table->foreignId('from_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();

            /* ── Batch & expiry ── */
            $table->string('batch_no', 80)->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('manufacturing_date')->nullable();

            /* ── Cost ── */
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);

            /* ── Polymorphic reference (links to PO, treatment, etc.) ── */
            $table->string('reference_type')->nullable();   // e.g. App\Models\PurchaseOrder
            $table->unsignedBigInteger('reference_id')->nullable();

            /* ── Audit ── */
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /* ── Indexes ── */
            $table->index('inventory_item_id');
            $table->index('movement_type');
            $table->index('created_at');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
