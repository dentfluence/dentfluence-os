<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_plans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consultation_id')
                  ->constrained('consultations')
                  ->cascadeOnDelete();

            // Two plan types per consultation are common (best vs acceptable)
            $table->enum('plan_type', ['best', 'acceptable']);

            // Array of { procedure: string, tooth: string|null, visits: int, cost: float }
            $table->json('rows');

            $table->decimal('total', 10, 2)->nullable();

            // Advance oral-care programme flag
            $table->boolean('aocp')->default(false);
            $table->text('aocp_plan')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('consultation_id');
            $table->index(['consultation_id', 'plan_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_plans');
    }
};
