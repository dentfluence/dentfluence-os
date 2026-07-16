<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Case Acceptance Engine — doctor-added options (per journey).
 *
 * The global decision tree is authored/owned by Dentfluence and never forked.
 * But a treating dentist may want to present a treatment from THIS clinic's
 * Treatment list that the generic tree doesn't include (e.g. an RCT + crown for
 * a specific case). This table holds those per-journey additions: a pointer to
 * a real Treatment (priced live by the Treatment Module) that shows up as an
 * extra option card alongside the authored tree options. Purely additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journey_custom_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_journey_id')->constrained('patient_journeys')->cascadeOnDelete();
            $table->foreignId('treatment_id')->constrained('treatments')->cascadeOnDelete();
            $table->string('label')->nullable();          // defaults to the treatment name
            $table->boolean('is_recommended')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['patient_journey_id', 'treatment_id'], 'journey_custom_options_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_custom_options');
    }
};
