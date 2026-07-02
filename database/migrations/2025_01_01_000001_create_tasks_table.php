<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            $table->string('title', 255);
            $table->text('description')->nullable();

            $table->foreignId('assigned_to')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->foreignId('created_by')
                  ->constrained('users')
                  ->restrictOnDelete();

            $table->unsignedBigInteger('branch_id');

            $table->foreignId('patient_id')
                  ->nullable()
                  ->constrained('patients')
                  ->nullOnDelete();

            $table->date('due_date');
            $table->time('due_time')->nullable();

            $table->enum('priority', ['urgent', 'high', 'medium', 'low'])
                  ->default('medium');

            $table->enum('category', ['clinical', 'admin', 'lab', 'follow_up', 'other'])
                  ->default('admin');

            $table->enum('status', ['pending', 'done', 'escalated'])
                  ->default('pending');

            $table->timestamp('done_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->text('escalation_note')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
