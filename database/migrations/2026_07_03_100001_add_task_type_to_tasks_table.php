<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3 — Work surfaces: Task Engine Human/System split
 *
 * Adds `task_type` to the tasks table so the Task Engine can tell apart:
 *   - 'human'  → a person must act on this (default — every existing task
 *                and every task created outside TaskEngine::autoCreate()
 *                stays 'human', so nothing already on a staff member's list
 *                disappears).
 *   - 'system' → a record of something Automation (RulesEngine / the
 *                Automation Engine) created via TaskEngine::autoCreate().
 *
 * Gated by the `tasks.human_system_split` flag (declared in
 * config/features.php, off by default). While the flag is off, reads are
 * unchanged — this migration only adds the column and backfills it.
 *
 * Backfill: any existing task that was created via TaskEngine::autoCreate()
 * or ReminderEngine::createReminder() left a trace in the Activity log
 * ('task.auto_created' or 'reminder.created', subject_type = Task). We use
 * that trace to retroactively tag those specific tasks 'system' — everything
 * else (manual, Practice Protocol, Lab, PO/Inventory, TreatmentVisit,
 * AppointmentReminderEngine, Tulip assistant) stays 'human', since a person
 * still has to act on those.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('task_type', 20)
                  ->default('human')
                  ->after('category');

            $table->index('task_type');
        });

        // ── Backfill: tag existing TaskEngine::autoCreate() tasks 'system' ──
        // Uses the Activity log trail left by TaskEngine/ReminderEngine, so we
        // never guess — only tasks with an explicit auto-creation record move.
        if (Schema::hasTable('activities')) {
            $taskIds = DB::table('activities')
                ->where('subject_type', \App\Models\Task::class)
                ->whereIn('event', ['task.auto_created', 'reminder.created'])
                ->pluck('subject_id')
                ->unique();

            if ($taskIds->isNotEmpty()) {
                DB::table('tasks')
                    ->whereIn('id', $taskIds)
                    ->update(['task_type' => 'system']);
            }
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['task_type']);
            $table->dropColumn('task_type');
        });
    }
};
