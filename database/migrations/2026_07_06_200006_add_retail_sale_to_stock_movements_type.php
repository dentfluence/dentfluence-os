<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adds 'retail_sale' as its own movement_type — a patient buying
     * toothpaste/brushes/OTC medicines off the shelf via their invoice,
     * distinct from 'stock_out' (manual dispense) and 'treatment_usage'
     * (consumed during a clinical procedure). Additive only; existing rows
     * are untouched.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE stock_movements MODIFY movement_type ENUM(
            'stock_in','stock_out','transfer','adjustment','expired','damaged',
            'treatment_usage','sterilization','maintenance','opening_stock','retail_sale'
        )");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE stock_movements MODIFY movement_type ENUM(
            'stock_in','stock_out','transfer','adjustment','expired','damaged',
            'treatment_usage','sterilization','maintenance','opening_stock'
        )");
    }
};
