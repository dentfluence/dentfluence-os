<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Point-in-time render cache taken when a presentation is Finalized.
     * NOT a competing source of truth for clinical/billing data — TreatmentPlan
     * and Invoice remain canonical. This just freezes what the patient was
     * actually shown, the same way an invoice PDF freezes billing state at
     * generation time. See §8 of docs/plan-smart-treatment-presentation.md.
     */
    public function up(): void
    {
        Schema::create('presentation_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_id')->constrained('presentations')->cascadeOnDelete();
            $table->json('snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_snapshots');
    }
};
