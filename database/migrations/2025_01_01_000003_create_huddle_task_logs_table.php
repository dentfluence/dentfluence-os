<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('huddle_task_logs', function (Blueprint $table) {
            $table->id();

            // References existing tasks table — never duplicates task logic
            $table->unsignedBigInteger('task_id');
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();

            // Which huddle card surfaces this task
            $table->foreignId('huddle_card_id')
                ->constrained('huddle_cards')
                ->cascadeOnDelete();

            // Who performed the action
            $table->unsignedBigInteger('performed_by');
            $table->foreign('performed_by')->references('id')->on('users');

            // Action recorded
            $table->enum('action', [
                'created',
                'assigned',
                'started',
                'completed',
                'blocked',
                'carried_forward',
                'escalated',
                'proof_uploaded',
                'auto_completed',  // CRM sync triggered completion
                'reopened',
            ]);

            // For proof_uploaded action
            $table->string('proof_path')->nullable();
            $table->timestamp('proof_uploaded_at')->nullable();

            // Optional note with the action
            $table->text('note')->nullable();

            // Metadata (e.g. carry forward reason, escalation target)
            $table->json('meta')->nullable();

            $table->timestamp('performed_at')->useCurrent();
            $table->timestamps();

            $table->index('task_id');
            $table->index('huddle_card_id');
            $table->index('performed_by');
            $table->index('performed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('huddle_task_logs');
    }
};
