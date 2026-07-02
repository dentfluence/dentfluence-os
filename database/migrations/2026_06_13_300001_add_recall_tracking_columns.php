<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — Recall Engine: tracking columns
 *
 * recall_queued_at is stamped on each source record when the engine creates
 * a communication_queue entry. The engine skips the record if:
 *   (a) recall_queued_at was within the cooldown window, OR
 *   (b) an open comm-queue item already exists for that patient+trigger combo.
 *
 * Tables touched:
 *   patients              — no_visit_recall cooldown
 *   treatment_plan_items  — approved plan trigger
 *   lab_cases             — received-but-not-booked trigger
 *   treatment_visits      — post-op / recent-treatment follow-up triggers
 */
return new class extends Migration
{
    public function up(): void
    {
        // Each block is guarded with hasColumn so the migration is safely re-runnable
        // if a previous attempt partially succeeded.

        // ── patients ──────────────────────────────────────────────────────
        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'recall_no_visit_queued_at')) {
                $table->timestamp('recall_no_visit_queued_at')->nullable()->after('next_recall_date');
            }
            if (!Schema::hasColumn('patients', 'recall_birthday_queued_at')) {
                $table->timestamp('recall_birthday_queued_at')->nullable()->after('recall_no_visit_queued_at');
            }
        });

        // ── treatment_plan_items ──────────────────────────────────────────
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            if (!Schema::hasColumn('treatment_plan_items', 'recall_queued_at')) {
                $table->timestamp('recall_queued_at')->nullable()->after('notes');
            }
        });

        // ── lab_cases ─────────────────────────────────────────────────────
        Schema::table('lab_cases', function (Blueprint $table) {
            if (!Schema::hasColumn('lab_cases', 'recall_queued_at')) {
                // lab_cases uses 'internal_notes', not 'notes'
                $table->timestamp('recall_queued_at')->nullable()->after('internal_notes');
            }
        });

        // ── treatment_visits ─────────────────────────────────────────────
        Schema::table('treatment_visits', function (Blueprint $table) {
            if (!Schema::hasColumn('treatment_visits', 'recall_queued_at')) {
                $table->timestamp('recall_queued_at')->nullable()->after('next_visit_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['recall_no_visit_queued_at', 'recall_birthday_queued_at']);
        });

        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->dropColumn('recall_queued_at');
        });

        Schema::table('lab_cases', function (Blueprint $table) {
            $table->dropColumn('recall_queued_at');
        });

        Schema::table('treatment_visits', function (Blueprint $table) {
            $table->dropColumn('recall_queued_at');
        });
    }
};
