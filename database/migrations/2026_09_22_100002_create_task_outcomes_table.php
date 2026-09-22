<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task Manager V2 — Slice 1b: the outcome trail.
 *
 * One row per thing that happened to a task. This is the answer to "no option
 * available to record the response" — markDone() previously flipped a boolean
 * and threw the reason away.
 *
 * APPEND-ONLY. Nothing in this table is ever updated or deleted; a mistake is
 * corrected by logging another row. That is what makes it usable as evidence
 * when a clinic asks "who said this patient was called, and what did they say".
 *
 * WHY outcome_label IS STORED ALONGSIDE outcome_key:
 * For call/whatsapp/follow_up tasks the key comes from action_option_lists,
 * which a clinic can rename in Settings > Call Outcomes. If we only stored the
 * key, renaming an outcome would silently rewrite history. The label is
 * snapshotted at the moment of logging, so what the staff member actually saw
 * on screen is what the trail shows a year later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_outcomes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('task_id')
                  ->constrained('tasks')
                  ->cascadeOnDelete();

            // Denormalised so branch-scoped reporting never has to join tasks.
            $table->unsignedBigInteger('branch_id')->index();

            // Who logged it. restrictOnDelete — a staff member leaving must not
            // erase the record of work they did.
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->restrictOnDelete();

            $table->enum('action', [
                'attempted',   // worked on, not finished — task stays open
                'rescheduled', // moved to a later date — task stays open
                'done',
                'cancelled',
                'reopened',    // a closed task put back on the list
            ]);

            // Nullable: 'reopened' and a bare 'done' carry no outcome key.
            $table->string('outcome_key', 60)->nullable();
            $table->string('outcome_label', 120)->nullable();

            $table->text('note')->nullable();

            // Only populated for action = 'rescheduled'.
            $table->date('due_date_before')->nullable();
            $table->date('due_date_after')->nullable();

            // created_at only — this table is append-only, there is no update.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_outcomes');
    }
};
