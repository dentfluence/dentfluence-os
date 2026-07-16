<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Case Acceptance Engine — sent snapshot (frozen §5.5 / §6).
 * IMMUTABLE, pinned at SEND: the fully-assembled block DTO + resolved prices +
 * versions + curation — the exact thing the patient sees. Live KB/price
 * changes never touch it. Edit-after-send creates a NEW journey + snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journey_sent_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_journey_id')->constrained('patient_journeys')->cascadeOnDelete();
            $table->json('snapshot');
            $table->decimal('estimate_total', 12, 2)->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->timestamps();

            $table->index('patient_journey_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_sent_snapshots');
    }
};
