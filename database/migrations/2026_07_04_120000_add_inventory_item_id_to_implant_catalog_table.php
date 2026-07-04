<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links each implant_catalog entry to a real, stock-tracked inventory_items row.
     *
     * Before this: implant_catalog was a pure reference list (brand/type/dimensions)
     * with no quantity anywhere — recording a placement never moved any stock.
     * After this: every catalog entry that represents a physical, countable component
     * (healing abutment, cover screw, coping, scan body, analogue, fixture, graft)
     * is paired 1:1 with an inventory_items row, so quantity flows through the
     * existing inventory_stocks / stock_movements ledger instead of a parallel system.
     *
     * Nullable because legacy/demo catalog rows created before this migration won't
     * have a paired item yet — the controller backfills one the next time the row is edited.
     */
    public function up(): void
    {
        Schema::table('implant_catalog', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')
                  ->nullable()
                  ->after('component_type')
                  ->constrained('inventory_items')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('implant_catalog', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_item_id');
        });
    }
};
