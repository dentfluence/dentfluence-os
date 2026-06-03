<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inventory Locations — physical areas within the clinic where stock is held.
     * Examples: Main Store, Operatory 1, Sterilization, Implant Drawer, Lab.
     * Future: supports multi-branch via clinic_id.
     */
    public function up(): void
    {
        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();   // e.g. MAIN-STORE, OP-1, STERIL
            $table->enum('type', [
                'main_store',
                'operatory',
                'sterilization',
                'lab',
                'storage',
                'implant_drawer',
                'other',
            ])->default('storage');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            // Future multi-branch: $table->foreignId('branch_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_locations');
    }
};
