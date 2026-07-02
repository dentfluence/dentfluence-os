<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Variants are the third tier of the product hierarchy.
     *
     *   Category  →  Sub-type       →  Variant
     *   Endodontics  Hand Files        #10, #15, #20, #25 …
     *   Composites   Universal Comp    Shade A1, A2, B1 …
     *
     * Managed in Inventory Settings (admin only).
     * Linked to inventory_items.variant_id (nullable, nullOnDelete).
     */
    public function up(): void
    {
        Schema::create('inventory_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_type_id')
                  ->constrained('inventory_sub_types')
                  ->cascadeOnDelete(); // deleting a sub-type removes its variants
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('sub_type_id');
            $table->unique(['sub_type_id', 'name']); // no duplicate variants per sub-type
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_variants');
    }
};
