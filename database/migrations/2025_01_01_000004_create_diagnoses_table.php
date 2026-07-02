<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consultation_id')
                  ->constrained('consultations')
                  ->cascadeOnDelete();

            $table->text('primary_diagnosis');
            $table->text('secondary_diagnosis')->nullable();

            $table->enum('risk_assessment', ['low', 'medium', 'high'])->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('consultation_id');
            $table->index('risk_assessment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnoses');
    }
};
