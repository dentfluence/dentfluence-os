<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which Clinical Library TreatmentMedia items are attached to a
     * presentation, and whether the dentist has chosen to include each one.
     * V1 only wires TreatmentMedia (treatment_id FK) — the other two library
     * subsystems (EducationMedia, ClinicalMedia) use inconsistent/fuzzy
     * linking today and are out of scope until that's cleaned up separately.
     */
    public function up(): void
    {
        Schema::create('presentation_media_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_id')->constrained('presentations')->cascadeOnDelete();
            $table->foreignId('treatment_media_id')->constrained('treatment_media')->cascadeOnDelete();
            $table->boolean('included')->default(true);
            $table->timestamps();

            $table->unique(['presentation_id', 'treatment_media_id'], 'presentation_media_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_media_items');
    }
};
