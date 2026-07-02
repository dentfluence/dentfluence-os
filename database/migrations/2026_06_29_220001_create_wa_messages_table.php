<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wa_messages — every individual WhatsApp message, in or out (Phase B item 1.2).
 * ----------------------------------------------------------------------------
 * One row per message. `direction` says which way it went. The text `body` is
 * encrypted at rest (Encrypted cast on the model) because patient messages can
 * contain health information (PHI) — consistent with the Phase A encryption work.
 * `payload` keeps the raw provider JSON for debugging / audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wa_thread_id')->constrained('wa_threads')->cascadeOnDelete();

            $table->string('channel', 20)->default('whatsapp');
            $table->string('direction', 10);                 // inbound | outbound

            // Provider's own message id (wamid...). Lets us match delivery/read
            // status callbacks back to the row. Nullable until the API returns it.
            $table->string('wa_message_id', 128)->nullable()->index();

            $table->string('from_phone', 32)->nullable();
            $table->string('to_phone', 32)->nullable();

            // text | template | image | document | audio | video | location | interactive
            $table->string('type', 20)->default('text');

            // The message text. ENCRYPTED at rest via the model's Encrypted cast.
            $table->text('body')->nullable();

            // Filled only for template messages (business-initiated, Chunk 4).
            $table->string('template_name', 128)->nullable();
            $table->json('template_payload')->nullable();

            // Media handling (used later — image/doc messages).
            $table->string('media_url')->nullable();
            $table->string('media_mime', 100)->nullable();

            // Lifecycle. Outbound: queued|sent|delivered|read|failed.
            // Inbound: received.
            $table->string('status', 20)->nullable();
            $table->text('error')->nullable();

            // Staff member who sent an outbound message (null = system/automation).
            $table->foreignId('sent_by_id')->nullable()->constrained('users')->nullOnDelete();

            // Raw provider JSON, kept for audit/debugging.
            $table->json('payload')->nullable();

            $table->timestamps();

            $table->index(['wa_thread_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_messages');
    }
};
