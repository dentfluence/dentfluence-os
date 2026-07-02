<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modules table — each sidebar section / feature area of Dentfluence OS.
     * Adding a new module in future = one new row, zero code changes.
     */
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name');         // Display: "Appointments"
            $table->string('slug')->unique(); // Machine: "appointments"
            $table->string('icon')->nullable(); // SVG path string for UI
            $table->string('section')->nullable(); // Grouping: "clinical", "operations", etc.
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
