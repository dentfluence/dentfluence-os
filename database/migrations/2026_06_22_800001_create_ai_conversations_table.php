<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Conversations — the assistant ("Tulip") core, A1.
 * ----------------------------------------------------------------------------
 * One row = one chat thread between a staff member and the assistant.
 *
 * Memory model: ONE shared brain, but PER-USER history — each conversation
 * belongs to the user who started it. A conversation can optionally be tied to
 * a screen's record (a Patient, Consultation, etc.) via the polymorphic
 * "context" columns, so the assistant knows what you were looking at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();

            // Who owns this thread (per-user memory).
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();

            // Optional: the record the chat is "about" (patient/consultation/etc.)
            $table->nullableMorphs('context'); // context_id + context_type

            // Short auto-generated title (from the first user message).
            $table->string('title')->nullable();

            // Which model answered this thread (qwen2.5:7b / llama3.1:8b).
            $table->string('model')->nullable();

            // active | archived
            $table->string('status')->default('active');

            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};
