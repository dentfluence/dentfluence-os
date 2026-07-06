<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks a product as sellable to patients over-the-counter (toothpaste,
     * brushes, flossers, OTC medicines) as distinct from clinical consumables
     * that are used up during treatment and never sold as a line item.
     * Independent of inventory_behavior — a product can be consumable AND
     * sellable (toothpaste) while most consumables are neither.
     */
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->boolean('is_sellable')->default(false)->after('is_reusable');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('is_sellable');
        });
    }
};
