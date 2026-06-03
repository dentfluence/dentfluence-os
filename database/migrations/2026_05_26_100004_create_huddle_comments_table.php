<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('huddle_comments', function (Blueprint $table) {
            $table->id();

            // Board the comment belongs to
            $table->foreignId('huddle_board_id')
                  ->constrained('huddle_boards')
                  ->cascadeOnDelete();

            // Optional: comment pinned to a specific card
            $table->foreignId('huddle_card_id')
                  ->nullable()
                  ->constrained('huddle_cards')
                  ->nullOnDelete();

            // Author
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Comment body
            $table->text('body');

            // 'comment' | 'hurdle' — hurdles appear in the Comments/Hurdles column
            $table->string('type', 30)->default('comment');

            // Threaded replies support
            $table->unsignedBigInteger('parent_id')->nullable();

            // Resolution tracking
            $table->boolean('is_resolved')->default(false);
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // ── Foreign keys ──────────────────────────────────────────────────
            $table->foreign('parent_id')
                  ->references('id')
                  ->on('huddle_comments')
                  ->nullOnDelete();

            $table->foreign('resolved_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            // ── Indexes ───────────────────────────────────────────────────────
            // Main read: unresolved comments for a board (Comments column)
            $table->index(
                ['huddle_board_id', 'is_resolved', 'created_at'],
                'huddle_comments_board_resolved_idx'
            );

            // Card-level comments (drawer)
            $table->index(['huddle_card_id', 'parent_id'], 'huddle_comments_card_idx');

            // Hurdle filter
            $table->index(['huddle_board_id', 'type'], 'huddle_comments_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('huddle_comments');
    }
};
