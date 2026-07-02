<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRM Update — Communication Activity Log table
 *
 * Auto-records all state changes on communication_queue rows.
 * Actions: created | edited | assigned | moved | closed | reminder_created
 * No manual logging — all writes happen via CommActivityLog::log()
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_activity_logs', function (Blueprint $table) {
            $table->id();

            // Which communication record this log entry belongs to
            $table->unsignedBigInteger('comm_id');

            // What happened
            $table->string('action');
            // created | edited | assigned | moved | closed | reminder_created

            // Human-readable description
            $table->text('description')->nullable();

            // Extra context (e.g. old/new status, assigned_to, move_to destination)
            $table->json('meta')->nullable();

            // Who did it
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable(); // denormalized for display

            // When it happened (separate from created_at for clarity)
            $table->timestamp('logged_at')->useCurrent();

            $table->timestamps();

            $table->index(['comm_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_activity_logs');
    }
};
