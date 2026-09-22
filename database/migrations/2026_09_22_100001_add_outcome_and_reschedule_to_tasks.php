<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task Manager V2 — Slice 1a: outcome, reschedule and cancel facts.
 *
 * DESIGN NOTE — why 'attempted' and 'rescheduled' are NOT statuses.
 *
 * `tasks.status` is read as `where('status','pending')` in HuddleService,
 * HuddleController, TodayActionsEngine, TaskEngine, LabAlertService,
 * CloseTwinRuleTasks and the Assistant tools. If an attempted or rescheduled
 * task carried its own status value, every one of those queries would stop
 * seeing it — the task would vanish from the Huddle board the moment reception
 * logged "no answer" on it. That is precisely the class of bug the 12 Sep
 * non-contact-outcome fix removed from the PRE side; we do not reintroduce it
 * here.
 *
 * So an attempted or rescheduled task stays `pending` (it is still open work).
 * What changes is what we KNOW about it:
 *   - attempt_count / last_outcome_key / last_outcome_at → "Attempted x2"
 *   - original_due_date / reschedule_count              → honest overdue
 * Only the two terminal states are statuses: `done` (already exists) and the
 * new `cancelled`.
 *
 * OVERDUE HONESTY: original_due_date is stamped on the FIRST reschedule only
 * and never moves again. Without it, staff can clear a red backlog simply by
 * pushing dates, and the owner loses the one number on the screen that is worth
 * reading.
 *
 * Additive and nullable/defaulted throughout — every existing task keeps
 * working with zero data repair, and `down()` is a clean drop.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. `cancelled` joins the terminal states. `escalated` is left exactly
        //    as it is — HuddleController counts it and links to it.
        DB::statement("
            ALTER TABLE tasks
            MODIFY COLUMN status ENUM('pending','done','escalated','cancelled')
            NOT NULL DEFAULT 'pending'
        ");

        Schema::table('tasks', function (Blueprint $table) {

            // ── Attempt / outcome facts (task stays pending) ────────────────
            $table->unsignedSmallInteger('attempt_count')
                  ->default(0)
                  ->after('status');

            // Free-form key. For call/whatsapp/follow_up it is a key from
            // action_option_lists (option_type = 'call_outcome') so Tasks and
            // the PRE board speak the same vocabulary; for lab / maintenance /
            // clinical / admin it is a key from Task::WORK_OUTCOMES.
            $table->string('last_outcome_key', 60)
                  ->nullable()
                  ->after('attempt_count');

            $table->timestamp('last_outcome_at')
                  ->nullable()
                  ->after('last_outcome_key');

            // ── Reschedule facts ────────────────────────────────────────────
            // Stamped once, on the first reschedule. Never updated again.
            $table->date('original_due_date')
                  ->nullable()
                  ->after('last_outcome_at');

            $table->unsignedSmallInteger('reschedule_count')
                  ->default(0)
                  ->after('original_due_date');

            // ── Cancel facts ────────────────────────────────────────────────
            $table->timestamp('cancelled_at')->nullable()->after('reschedule_count');
            $table->text('cancel_reason')->nullable()->after('cancelled_at');

            // Board queries are "open work for this branch, by due date".
            $table->index(['branch_id', 'status', 'due_date'], 'tasks_branch_status_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_branch_status_due_idx');
            $table->dropColumn([
                'attempt_count',
                'last_outcome_key',
                'last_outcome_at',
                'original_due_date',
                'reschedule_count',
                'cancelled_at',
                'cancel_reason',
            ]);
        });

        // Any task sitting in the state being removed becomes pending again —
        // dropping it silently would leave an unreadable enum value behind.
        DB::table('tasks')->where('status', 'cancelled')->update(['status' => 'pending']);

        DB::statement("
            ALTER TABLE tasks
            MODIFY COLUMN status ENUM('pending','done','escalated')
            NOT NULL DEFAULT 'pending'
        ");
    }
};
