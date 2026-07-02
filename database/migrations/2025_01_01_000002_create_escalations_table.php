<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('task_id')
                  ->constrained('tasks')
                  ->cascadeOnDelete();

            $table->foreignId('escalated_by')
                  ->constrained('users')
                  ->restrictOnDelete();

            $table->foreignId('escalated_to')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->text('reason');

            $table->unsignedBigInteger('branch_id');

            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalations');
    }
};
