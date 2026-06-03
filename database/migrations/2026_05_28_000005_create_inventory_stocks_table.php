<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inventory Stocks — LIVE calculated stock per item per location.
     *
     * THIS TABLE IS NEVER EDITED DIRECTLY IN APPLICATION CODE.
     * It is always updated as a side-effect of creating a stock_movement record.
     * Think of it as a materialised view of the stock_movements ledger.
     *
     * available_qty = total received - total consumed - damaged - expired
     * reserved_qty  = qty reserved for pending treatments (future feature)
     */
    public function up(): void
    {
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->decimal('available_qty', 10, 2)->default(0);
            $table->decimal('reserved_qty', 10, 2)->default(0);   // future: treatment reservations
            $table->timestamps();

            $table->unique(['inventory_item_id', 'location_id']);
            $table->index('inventory_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stocks');
    }
};
