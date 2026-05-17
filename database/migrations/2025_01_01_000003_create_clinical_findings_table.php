<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_findings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consultation_id')
                  ->constrained('consultations')
                  ->cascadeOnDelete();

            // Soft-tissue & hard-tissue findings
            $table->text('soft_tissue')->nullable();
            $table->text('caries')->nullable();
            $table->text('periodontal')->nullable();

            // Periodontal indices
            $table->text('bleeding_on_probing')->nullable();
            $table->text('plaque_index')->nullable();

            // Occlusion / TMJ
            $table->text('occlusion')->nullable();
            $table->text('tmj')->nullable();

            // Restorative / prosthetic status
            $table->text('existing_condition')->nullable();

            // Hygiene assessment
            $table->text('oral_hygiene')->nullable();

            // FDI tooth-chart — array of { tooth: int, conditions: string[] }
            $table->json('chart_data')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index — one record is expected per consultation, but not enforced
            // at DB level so a second draft can be saved before replacing.
            $table->index('consultation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_findings');
    }
};
