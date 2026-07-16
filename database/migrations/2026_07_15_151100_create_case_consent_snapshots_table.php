<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Case Acceptance Engine — consent snapshot (frozen §5.5 / §6).
 * IMMUTABLE, pinned at ACCEPT: what the patient confirmed (shown + chosen +
 * prices at accept) with IP / user-agent for auditability.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_consent_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_journey_id')->constrained('patient_journeys')->cascadeOnDelete();
            $table->json('snapshot');
            $table->decimal('estimate_total', 12, 2)->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index('patient_journey_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_consent_snapshots');
    }
};
