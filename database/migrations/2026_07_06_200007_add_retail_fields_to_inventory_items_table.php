<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the two fields collected only by the Saleable/FMCG product tab:
     * a simple fixed retail-type label (Toothpaste, Brush, etc. — deliberately
     * NOT the clinical Category taxonomy) and a single expiry date on the
     * product master itself (FMCG items are usually tracked as one running
     * batch at counter-sale scale, unlike clinical consumables where expiry
     * is captured per lot at GRN/stock-in).
     */
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('retail_type', 40)->nullable()->after('is_sellable');
            $table->date('retail_expiry_date')->nullable()->after('retail_type');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn(['retail_type', 'retail_expiry_date']);
        });
    }
};
