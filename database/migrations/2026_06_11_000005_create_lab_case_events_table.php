<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab Module v2 — Case Timeline / Audit Trail
 *
 * Append-only log of everything that happens to a lab case.
 * Rows are NEVER updated or deleted (no updated_at, no soft deletes).
 * Powers the case timeline UI and the enterprise audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_case_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_case_id')->constrained()->cascadeOnDelete();

            // created | status_changed | updated | attachment_added | attachment_removed
            // expense_linked | printed | whatsapp_sent | duplicated | archived | restored | note
            $table->string('event_type')->index();

            // For status_changed events
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();

            $table->string('description');               // human-readable line for the timeline
            $table->json('meta')->nullable();            // extra structured data (changed fields, file name, ...)

            // Who did it
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('created_at')->useCurrent(); // append-only: created_at only

            $table->index(['lab_case_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_case_events');
    }
};
