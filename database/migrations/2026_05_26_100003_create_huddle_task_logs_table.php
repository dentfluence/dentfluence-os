<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * huddle_task_logs
     *
     * Huddle-specific metadata for tasks that appear on the board.
     * Does NOT duplicate tasks table data — only stores what's unique
     * to the huddle context (proof, carry-forward, board linkage).
     *
     * NOTE: The HuddleTaskRepository currently references both
     * huddle_board_id and huddle_card_id in different methods.
     * This table includes BOTH columns to support both patterns.
     * The card linkage (huddle_card_id) is the primary FK;
     * huddle_board_id is a denormalized convenience for board-level queries.
     */
    public function up(): void
    {
        Schema::create('huddle_task_logs', function (Blueprint $table) {
            $table->id();

            // FK → existing tasks table (never duplicated, just referenced)
            $table->unsignedBigInteger('task_id');

            // Primary linkage: which huddle card represents this task
            $table->foreignId('huddle_card_id')
                  ->nullable()
                  ->constrained('huddle_cards')
                  ->nullOnDelete();

            // Denormalized for board-level queries (avoids joining through cards)
            $table->unsignedBigInteger('huddle_board_id')->nullable();

            // Who performed the logged action
            $table->unsignedBigInteger('performed_by')->nullable();

            // Action type: completed | proof_uploaded | reassigned |
            //              carried_forward | auto_completed | escalated
            $table->string('action', 50)->nullable();

            // Proof file path (required for sterilization task type)
            $table->string('proof_path', 500)->nullable();
            $table->timestamp('proof_uploaded_at')->nullable();

            // Free-form note attached to this log entry
            $table->text('note')->nullable();

            // Arbitrary extra data (e.g. old/new assignee for reassign logs)
            $table->json('meta')->nullable();

            // When the action actually happened (can differ from created_at)
            $table->timestamp('performed_at')->nullable();

            // Carry-forward flag — set by CarryForwardTasksJob
            $table->boolean('carried_forward')->default(false);

            // Status snapshot at time of log
            // pending | in_progress | done | overdue | blocked
            $table->string('status', 30)->default('pending');

            $table->timestamps();

            // ── Foreign keys ──────────────────────────────────────────────────
            $table->foreign('task_id')
                  ->references('id')
                  ->on('tasks')
                  ->cascadeOnDelete();

            $table->foreign('performed_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            $table->foreign('huddle_board_id')
                  ->references('id')
                  ->on('huddle_boards')
                  ->nullOnDelete();

            // ── Indexes ───────────────────────────────────────────────────────
            // Board-level task list (HuddleTaskRepository::forBoard)
            $table->index(['huddle_board_id', 'status'], 'huddle_task_logs_board_status_idx');

            // Idempotent lookup (findByTaskAndBoard)
            $table->index(['task_id', 'huddle_board_id'], 'huddle_task_logs_task_board_idx');

            // Carry-forward job (overdueForBranch traverses board → task logs)
            $table->index('carried_forward', 'huddle_task_logs_carry_forward_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('huddle_task_logs');
    }
};
