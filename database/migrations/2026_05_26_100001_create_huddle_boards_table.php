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

            $table->unsignedBigInteger('branch_id');

            $table->string('role', 50);
            $table->date('date');

            $table->string('title', 255)->nullable();

            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['branch_id', 'role', 'date'], 'huddle_boards_branch_role_date_unique');

            $table->foreign('locked_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['branch_id', 'role', 'date'], 'huddle_boards_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('huddle_boards');
    }
};
