<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0 — Domain-event idempotency ledger.
 *
 * Additive, non-destructive. Records that a given subscriber has processed a
 * given event, so re-delivery is safe (Red-Team condition: idempotent events).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('processed_domain_events')) {
            return;
        }

        Schema::create('processed_domain_events', function (Blueprint $table) {
            $table->id();

            $table->uuid('event_id')->index();     // DomainEvent::eventId()
            $table->string('subscriber')->index(); // logical subscriber key
            $table->string('event_name')->nullable();
            $table->dateTime('processed_at');

            $table->timestamps();

            // A subscriber processes any given event at most once.
            $table->unique(['event_id', 'subscriber']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_domain_events');
    }
};
