<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Implant Registry — Session 8
 * Stores the catalog of implant components:
 *   Fixtures, Abutments, Healing Abutments, Analogues, Scan Bodies, Copings, Grafts
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('implant_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('brand', 100);                        // e.g. Nobel Biocare, Straumann
            $table->string('system', 100)->nullable();           // e.g. NobelActive, BLX
            $table->string('component_type', 60);                // fixture|abutment|healing_abutment|analogue|scan_body|coping|graft|other
            $table->string('product_code', 100)->nullable();     // Manufacturer's product code
            $table->string('description', 255)->nullable();
            $table->string('diameter_mm', 30)->nullable();       // e.g. 3.5, 4.1, 4.8
            $table->string('length_mm', 30)->nullable();         // e.g. 8, 10, 12
            $table->string('platform', 60)->nullable();          // e.g. NP, RP, WP
            $table->string('material', 80)->nullable();          // e.g. Ti Grade IV, PEEK, Zirconia
            $table->string('photo_path', 500)->nullable();       // Local storage path
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('brand');
            $table->index('component_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('implant_catalog');
    }
};
