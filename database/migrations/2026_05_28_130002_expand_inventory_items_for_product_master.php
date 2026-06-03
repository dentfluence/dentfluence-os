<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add Product Master fields to inventory_items.
     * Existing columns retained: product_name, generic_name, brand, description,
     * image, category_id, usage_type, mrp, minimum_qty, last_purchase_price, is_active.
     */
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {

            // Sub-type (linked to Settings > Sub-types)
            if (! Schema::hasColumn('inventory_items', 'sub_type_id')) {
                $table->foreignId('sub_type_id')
                      ->nullable()
                      ->after('category_id')
                      ->constrained('inventory_sub_types')
                      ->nullOnDelete();
            }

            // Packaging
            if (! Schema::hasColumn('inventory_items', 'packaging_type')) {
                $table->string('packaging_type', 60)->nullable()->after('usage_type');
                // e.g. Syringe, Bottle, Box, Strip, Vial, Sachet, Kit
            }
            if (! Schema::hasColumn('inventory_items', 'qty_in_packaging')) {
                $table->decimal('qty_in_packaging', 8, 2)->nullable()->after('packaging_type');
                // e.g. 4 (combined with packaging_unit → "4 g")
            }
            if (! Schema::hasColumn('inventory_items', 'packaging_unit_label')) {
                $table->string('packaging_unit_label', 20)->nullable()->after('qty_in_packaging');
                // e.g. g, ml, pcs, mg
            }
            if (! Schema::hasColumn('inventory_items', 'pack_size_label')) {
                $table->string('pack_size_label', 80)->nullable()->after('packaging_unit_label');
                // human-readable e.g. "1 Syringe", "10 Strips of 10"
            }
            if (! Schema::hasColumn('inventory_items', 'shelf_life_months')) {
                $table->unsignedSmallInteger('shelf_life_months')->nullable()->after('pack_size_label');
            }

            // Company & Brand (brand already exists; add company and alternatives)
            if (! Schema::hasColumn('inventory_items', 'company_name')) {
                $table->string('company_name', 100)->nullable()->after('brand');
                // manufacturer e.g. 3M, Ivoclar, GC
            }
            if (! Schema::hasColumn('inventory_items', 'alternative_brands')) {
                $table->json('alternative_brands')->nullable()->after('company_name');
                // ["Tetric N Ceram (Ivoclar)", "Herculite (Kerr)"]
            }
            if (! Schema::hasColumn('inventory_items', 'preferred_brand')) {
                $table->string('preferred_brand', 100)->nullable()->after('alternative_brands');
            }

            // Pricing (purchase_price & mrp already exist; add last_purchase_date)
            if (! Schema::hasColumn('inventory_items', 'last_purchase_date')) {
                $table->date('last_purchase_date')->nullable()->after('mrp');
            }

            // Stock rules (minimum_qty already exists; add reorder_level)
            if (! Schema::hasColumn('inventory_items', 'reorder_level')) {
                $table->decimal('reorder_level', 10, 2)->default(0)->after('minimum_qty');
            }

            // Treatment tags (JSON — e.g. ["Composite Filling","Posterior Restorations"])
            if (! Schema::hasColumn('inventory_items', 'treatment_tags')) {
                $table->json('treatment_tags')->nullable()->after('reorder_level');
            }

            // Notes
            if (! Schema::hasColumn('inventory_items', 'product_notes')) {
                $table->text('product_notes')->nullable()->after('treatment_tags');
                // named product_notes to avoid clash with existing description
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $cols = [
                'sub_type_id', 'packaging_type', 'qty_in_packaging', 'packaging_unit_label',
                'pack_size_label', 'shelf_life_months', 'company_name', 'alternative_brands',
                'preferred_brand', 'last_purchase_date', 'reorder_level', 'treatment_tags',
                'product_notes',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('inventory_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
