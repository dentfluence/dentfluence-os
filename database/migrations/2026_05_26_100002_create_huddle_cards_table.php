<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('huddle_cards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('huddle_board_id')
                  ->constrained('huddle_boards')
                  ->cascadeOnDelete();

            // ── Source reference ──────────────────────────────────────────────
            // 'appointment' | 'task' — never stores the actual record,
            // just a pointer to the existing table row
            $table->string('source_type', 50);   // appointment | task
            $table->unsignedBigInteger('source_id');

            // ── Card classification ───────────────────────────────────────────
            // 'patient_flow' | 'task' | 'lab' | 'comms' | 'quick_action'
            $table->string('card_type', 50);

            // ── Board positioning ─────────────────────────────────────────────
            // Which kanban column this card belongs to
            $table->string('column_key', 50);    // today_flow | tasks | lab | etc.
            $table->unsignedSmallInteger('position')->default(0);

            // ── Card state ────────────────────────────────────────────────────
            // pending | in_progress | done | overdue | blocked | cancelled
            $table->string('status', 30)->default('pending');

            // Cached snapshot of source data — avoids repeated joins at render time.
            // Refreshed by UpdateHuddleCard listener when source changes.
            // NOTE: column is named 'snapshot' on the model but we also support
            // 'payload' alias in the aggregation service — both map here.
            $table->json('snapshot')->nullable();

            // Doctor/front-desk instruction note on this card
            $table->text('instruction')->nullable();

            // Staff member this card is assigned to (optional, FK to users)
            $table->unsignedBigInteger('assigned_to')->nullable();

            // ── Flags ─────────────────────────────────────────────────────────
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_carried_forward')->default(false);
            $table->date('carried_from_date')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // ── Foreign keys ──────────────────────────────────────────────────
            $table->foreign('assigned_to')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            // ── Indexes ───────────────────────────────────────────────────────
            // Idempotent upsert in AggregationService (firstOrCreateFromSource)
            $table->index(['huddle_board_id', 'source_type', 'source_id'], 'huddle_cards_source_idx');

            // Column renderer — most frequent read
            $table->index(['huddle_board_id', 'column_key', 'position'], 'huddle_cards_column_idx');

            // Status-based filtering (overdue, flagged, etc.)
            $table->index(['huddle_board_id', 'status'], 'huddle_cards_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('huddle_cards');
    }
};
