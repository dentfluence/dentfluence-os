<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Action Logs — the audit trail, A1.
 * ----------------------------------------------------------------------------
 * Because the assistant has FULL POWER for all staff, every tool it runs is
 * recorded here: what it read, what it wrote, on which record, with what
 * arguments, and whether a confirm card was required/approved.
 *
 * This is the safety net for agentic actions — a complete "who/what/when" of
 * everything the AI did, so nothing happens invisibly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_action_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ai_conversation_id')->nullable()
                  ->constrained('ai_conversations')->nullOnDelete();
            $table->foreignId('ai_message_id')->nullable()
                  ->constrained('ai_messages')->nullOnDelete();

            // The tool that ran, e.g. 'find_patient', 'create_reminder'.
            $table->string('tool_name');
            // read | write | clinical | financial  — drives confirm-card rules.
            $table->string('category')->default('read');
            // Human-readable one-liner: "Read summary for patient DF-00042".
            $table->string('summary')->nullable();

            // The record the action targeted (polymorphic, optional).
            $table->nullableMorphs('target'); // target_id + target_type

            // The arguments passed to the tool.
            $table->json('payload')->nullable();

            // pending_confirmation | success | failed | rejected
            $table->string('result')->default('success');
            $table->text('error')->nullable();

            // Confirm-card tracking (clinical/financial writes).
            $table->boolean('requires_confirmation')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('category');
            $table->index('result');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_action_logs');
    }
};
