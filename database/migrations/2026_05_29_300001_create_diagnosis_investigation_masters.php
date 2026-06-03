<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('diagnosis_masters')) {
            Schema::create('diagnosis_masters', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('icd_code')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('investigation_masters')) {
            Schema::create('investigation_masters', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('unit')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('investigation_masters');
        Schema::dropIfExists('diagnosis_masters');
    }
};
