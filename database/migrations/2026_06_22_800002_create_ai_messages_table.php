<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Messages — one row per turn in a conversation, A1.
 * ----------------------------------------------------------------------------
 * Stores the running transcript the model needs as "memory": system primer,
 * user messages, assistant replies, and tool calls/results (for the agentic
 * side built in later phases).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ai_conversation_id')
                  ->constrained('ai_conversations')
                  ->cascadeOnDelete();

            // Who sent a 'user' message (null for system/assistant/tool turns).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // system | user | assistant | tool
            $table->string('role');

            // The text content of the turn.
            $table->longText('content')->nullable();

            // ── Agentic fields (used from Phase D; nullable now) ──────────────
            // Tools the assistant asked to run this turn.
            $table->json('tool_calls')->nullable();
            // For role=tool: which tool ran and what it returned.
            $table->string('tool_name')->nullable();
            $table->json('tool_result')->nullable();

            // Bookkeeping
            $table->string('model')->nullable();
            $table->unsignedInteger('tokens')->nullable();

            $table->timestamps();

            $table->index(['ai_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};
