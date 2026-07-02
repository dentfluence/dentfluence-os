<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Communication System: Attempt Tracking + Mandatory Outcome + SLA Fields
 *
 * New columns:
 *   source_engine     — which engine generated this item (manual|inbound|recall|opportunity|b2b)
 *   opportunity_value — ₹ potential value (for Opportunity Engine, Phase 2)
 *   attempt_count     — how many times staff have tried to reach this person
 *   last_attempt_at   — timestamp of last attempt
 *   sla_deadline      — when this must be acted on (30min for inbound, 24h default)
 *   sla_breached      — flag set when now() > sla_deadline and still open
 *   outcome           — mandatory on close (appointment_booked|unreachable|lost|etc.)
 *   outcome_reason    — free text context for the outcome
 *   response_notes    — staff notes after each attempt (last note wins, full log in comm_activity_logs)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_queue', function (Blueprint $table) {

            // ── Engine / value ──────────────────────────────────────────
            $table->string('source_engine')->default('manual')->after('move_to');
            // manual | inbound | recall | opportunity | b2b

            $table->decimal('opportunity_value', 10, 2)->nullable()->after('source_engine');
            // ₹ potential value; populated by Opportunity Engine (Phase 2)

            // ── Attempt tracking ────────────────────────────────────────
            $table->unsignedTinyInteger('attempt_count')->default(0)->after('opportunity_value');
            $table->timestamp('last_attempt_at')->nullable()->after('attempt_count');

            // ── SLA ─────────────────────────────────────────────────────
            $table->timestamp('sla_deadline')->nullable()->after('last_attempt_at');
            // Set on create: inbound = +30min, high priority = +60min, default = +24h

            $table->boolean('sla_breached')->default(false)->after('sla_deadline');
            // Set true when now() > sla_deadline and status != closed

            // ── Outcome (mandatory on close) ────────────────────────────
            $table->string('outcome')->nullable()->after('sla_breached');
            // appointment_booked | treatment_started | follow_up_set |
            // not_interested | unreachable | lost | escalated | spam

            $table->text('outcome_reason')->nullable()->after('outcome');
            // Free text — why lost, what was said, etc.

            // ── Response notes ──────────────────────────────────────────
            $table->text('response_notes')->nullable()->after('outcome_reason');
            // Last attempt note; full history is in comm_activity_logs
        });
    }

    public function down(): void
    {
        Schema::table('communication_queue', function (Blueprint $table) {
            $table->dropColumn([
                'source_engine',
                'opportunity_value',
                'attempt_count',
                'last_attempt_at',
                'sla_deadline',
                'sla_breached',
                'outcome',
                'outcome_reason',
                'response_notes',
            ]);
        });
    }
};
