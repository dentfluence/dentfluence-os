<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operatories — physical treatment chairs/rooms in a clinic.
 * Completely independent table; existing modules are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operatories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');          // clinic/branch scope
            $table->string('name', 100);                      // e.g. "Chair 1", "Implant Room"
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->index(['branch_id', 'is_active', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operatories');
    }
};
