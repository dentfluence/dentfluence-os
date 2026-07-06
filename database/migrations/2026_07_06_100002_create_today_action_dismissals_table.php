<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suppresses one occurrence of a live-computed Today's Actions row for the
 * day it was dismissed. Only used for categories with no backing queue row
 * of their own (opportunities, appointment_reminders, birthdays,
 * pending_estimates, membership_renewals, lab_ready, payment_reminders, ...).
 * `recall_calls` / `missed_calls_yesterday` are backed by `communication_queue`
 * and use its existing ignore()/dismiss() methods instead — see
 * TodayActionsEngine and docs/feature-specs/feature-spec-action-board-dismiss.md.
 *
 * Deliberately date-scoped, not permanent: if the same underlying condition
 * (e.g. an opportunity still overdue) is still true tomorrow, the row
 * reappears. "Not today", not "never show me this again" — permanent
 * suppression is what the existing per-category "ignore" is for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('today_action_dismissals', function (Blueprint $table) {
            $table->id();
            $table->string('category', 60);
            $table->string('subject_type', 150);
            $table->unsignedBigInteger('subject_id');
            $table->date('dismissed_for_date');
            $table->string('reason_key', 60);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('dismissed_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['category', 'subject_type', 'subject_id', 'dismissed_for_date'],
                'today_action_dismissals_unique_occurrence'
            );
            $table->index(['category', 'dismissed_for_date'], 'today_action_dismissals_lookup');

            $table->foreign('dismissed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('today_action_dismissals');
    }
};
