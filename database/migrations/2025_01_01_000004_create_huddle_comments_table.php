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

            // Comments can be on a board (general) or a specific card
            $table->foreignId('huddle_board_id')
                ->constrained('huddle_boards')
                ->cascadeOnDelete();

            $table->foreignId('huddle_card_id')
                ->nullable()
                ->constrained('huddle_cards')
                ->nullOnDelete();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users');

            $table->text('body');

            // Comment type / purpose
            $table->enum('type', [
                'general',    // board-level general comment
                'hurdle',     // blocking issue
                'win',        // positive callout
                'concern',    // staff concern
                'followup',   // needs follow-up
            ])->default('general');

            // Threaded replies
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('huddle_comments')
                ->nullOnDelete();

            // Resolution
            $table->boolean('is_resolved')->default(false);
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->foreign('resolved_by')->references('id')->on('users');
            $table->timestamp('resolved_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('huddle_board_id');
            $table->index('huddle_card_id');
            $table->index('user_id');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('huddle_comments');
    }
};
