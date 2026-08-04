<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P0-2 hardening (2026-08-04): placing a catalog-linked implant now
     * creates a real stock_movement (see InventoryService::createPlacement).
     * This column links the placement back to that movement, the same
     * traceability pattern grn_items.stock_movement_id already uses for
     * purchase receipts.
     */
    public function up(): void
    {
        Schema::table('implant_placements', function (Blueprint $table) {
            $table->foreignId('stock_movement_id')->nullable()->after('label_photo_path')
                ->constrained('stock_movements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('implant_placements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_movement_id');
        });
    }
};
