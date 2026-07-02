<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sub-types belong to a category (e.g. Category: Restorative → Sub-types: Composite, GIC, Amalgam).
     * Managed in Inventory Settings (admin only).
     */
    public function up(): void
    {
        Schema::create('inventory_sub_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                  ->constrained('inventory_categories')
                  ->cascadeOnDelete();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('category_id');
            $table->unique(['category_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_sub_types');
    }
};
