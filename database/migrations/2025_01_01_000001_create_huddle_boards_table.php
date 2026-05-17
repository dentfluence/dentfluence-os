<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('huddle_boards', function (Blueprint $table) {
            $table->id();

            // Branch scoping — multi-branch ready, no FK to avoid cross-DB issues
            // branch_id is referenced from users.branch_id (same integer space)
            $table->unsignedBigInteger('branch_id');

            // Which role sees this board
            $table->enum('role', ['admin', 'doctor', 'front_desk', 'assistant']);

            // The date this board represents
            $table->date('date');

            // Board-level metadata
            $table->string('title')->default('Daily Huddle');
            $table->boolean('is_locked')->default(false); // lock after EOD
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable(); // FK → users.id

            $table->timestamps();

            // One board per branch + role + date
            $table->unique(['branch_id', 'role', 'date'], 'huddle_boards_unique');

            $table->index('branch_id');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('huddle_boards');
    }
};
