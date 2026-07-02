<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wa_threads — one conversation per contact (Phase B item 1.2, WhatsApp two-way).
 * ----------------------------------------------------------------------------
 * Each row = a single ongoing chat with one phone number. Inbound and outbound
 * messages (in wa_messages) both hang off a thread. A thread can optionally be
 * linked to a known Patient and/or a PRM Lead, so the unified inbox can show who
 * the person is. Channel column is included so the same structure can later cover
 * SMS / Instagram DMs etc. without a rebuild.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_threads', function (Blueprint $table) {
            $table->id();

            // Which channel this conversation runs on (whatsapp for now).
            $table->string('channel', 20)->default('whatsapp');

            // Contact's phone in normalized digits-only form (E.164 without '+'),
            // e.g. 919876543210. This is how we match inbound messages to a thread.
            $table->string('contact_phone', 32);
            $table->string('contact_name')->nullable();

            // Optional links to known records. nullOnDelete = if the patient/lead is
            // removed, the chat history stays but the link clears (we never lose audit).
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();

            // open | closed | archived — front-desk workflow state.
            $table->string('status', 20)->default('open');

            // Encrypted short snippet of the latest message, for the inbox list.
            // (text column because ciphertext is longer than the plaintext.)
            $table->text('last_preview')->nullable();

            // Activity timestamps used to sort the inbox and drive the 24h rule.
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->string('last_direction', 10)->nullable(); // inbound | outbound

            // Meta's 24-hour "customer service window": free-text business replies are
            // only allowed while this is in the future. Set each time a patient messages
            // in. Outside the window you must send a pre-approved template (Chunk 4).
            $table->timestamp('window_expires_at')->nullable();

            // How many inbound messages the staff hasn't opened yet.
            $table->unsignedInteger('unread_count')->default(0);

            // Optional staff assignment (no-op today under single-login admin).
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // One thread per (channel + phone). Also speeds up inbound matching.
            $table->unique(['channel', 'contact_phone']);
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_threads');
    }
};
