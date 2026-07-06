<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links an invoice line to the retail product it sold (toothpaste,
     * brushes, OTC medicines etc.), so the sale can auto-deduct stock.
     * Nullable — most invoice lines are treatments with no inventory item.
     * nullOnDelete so removing a product from the catalogue never deletes
     * historical invoice lines.
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->after('treatment_plan_item_id')
                  ->constrained('inventory_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['inventory_item_id']);
            $table->dropColumn('inventory_item_id');
        });
    }
};
