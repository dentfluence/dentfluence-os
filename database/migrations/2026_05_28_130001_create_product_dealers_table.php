<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Many-to-many: a product can have multiple dealers/vendors.
     * is_primary flags the main dealer; is_alternate flags backup suppliers.
     */
    public function up(): void
    {
        Schema::create('product_dealers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')
                  ->constrained('inventory_items')
                  ->cascadeOnDelete();
            $table->foreignId('vendor_id')
                  ->constrained('inventory_vendors')
                  ->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_alternate')->default(false);
            $table->timestamps();

            $table->unique(['inventory_item_id', 'vendor_id']);
            $table->index('inventory_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_dealers');
    }
};
