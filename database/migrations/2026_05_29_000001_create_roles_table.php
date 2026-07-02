<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles table — defines named roles (admin, manager, doctor, etc.)
     * Replaces the simple string `role` column on users with a proper FK.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');                     // Display: "Clinic Manager"
            $table->string('slug')->unique();           // Machine: "manager"
            $table->string('description')->nullable();
            $table->string('color', 7)->default('#6a0f70'); // Hex badge colour
            $table->boolean('is_system')->default(false);   // System roles can't be deleted
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
