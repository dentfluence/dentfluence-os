<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inventory Items — the master product catalogue.
     *
     * Key design decisions:
     * - inventory_behavior: consumable | reusable | semi_reusable
     * - packaging conversion: purchase_unit → consumption_unit via pieces_per_unit
     * - expiry fields live here as flags; actual batch/expiry lives on stock_movements
     * - direct qty editing is NEVER allowed — all changes go through stock_movements
     */
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();

            /* ── Identity ── */
            $table->string('item_code', 50)->unique();
            $table->string('product_name');
            $table->string('generic_name')->nullable();
            $table->string('brand')->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();

            /* ── Classification ── */
            $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('inventory_categories')->nullOnDelete();

            /* ── Behavior ── */
            $table->enum('inventory_behavior', ['consumable', 'reusable', 'semi_reusable'])->default('consumable');
            $table->enum('usage_type', ['single_use', 'multiple_use'])->default('single_use');

            /* ── Packaging & conversion ── */
            $table->string('purchase_unit', 40)->default('box');      // e.g. box, vial, pack
            $table->string('consumption_unit', 40)->default('piece'); // e.g. piece, ml, tablet
            $table->unsignedSmallInteger('pieces_per_unit')->default(1); // conversion factor

            /* ── Pricing ── */
            $table->decimal('last_purchase_price', 10, 2)->default(0);
            $table->decimal('average_purchase_price', 10, 2)->default(0);
            $table->decimal('cost_per_usage', 10, 4)->default(0);
            $table->decimal('mrp', 10, 2)->nullable();
            $table->decimal('gst_rate', 5, 2)->default(0); // GST percentage

            /* ── Stock rules ── */
            $table->decimal('minimum_qty', 10, 2)->default(0);
            $table->decimal('minimum_order_qty', 10, 2)->default(1);

            /* ── Expiry management ── */
            $table->boolean('has_expiry')->default(false);
            $table->unsignedSmallInteger('expiry_alert_days')->default(90); // warn before N days

            /* ── Reusable flags (detailed tracking on reusable_assets table) ── */
            $table->boolean('is_reusable')->default(false);
            $table->enum('tracking_type', ['usage_based', 'sterilization_based', 'time_based'])->nullable();
            $table->unsignedSmallInteger('max_usage_count')->nullable();
            $table->boolean('sterilization_required')->default(false);

            /* ── Meta ── */
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            /* ── Indexes ── */
            $table->index('category_id');
            $table->index('inventory_behavior');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
