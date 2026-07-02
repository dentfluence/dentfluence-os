<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — Relationship Engine: Automation
 *
 * Tracks every time a RulesEngine rule fires for a relationship.
 * Used by RulesEngine::checkCooldown() to prevent duplicate rule firings
 * within the configured cooldown window.
 *
 * Also provides a full audit trail: "Why was this task created?" →
 * look up the rule_log for that relationship.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relationship_rule_logs', function (Blueprint $table) {
            $table->id();

            // Which rule fired (matches key in config/relationship_rules.php['rules'])
            $table->string('rule_name')->index();

            // The relationship this rule fired for
            $table->foreignId('relationship_id')
                  ->constrained('relationships')
                  ->cascadeOnDelete();

            // The subject that triggered the rule (polymorphic: Treatment, Appointment, etc.)
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // When the rule actually fired
            $table->dateTime('fired_at')->index();

            // Any additional context (action taken, conditions matched, etc.)
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Composite index for cooldown queries: "did this rule fire for this relationship recently?"
            $table->index(['rule_name', 'relationship_id', 'fired_at']);

            // Index for polymorphic subject lookups
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relationship_rule_logs');
    }
};
