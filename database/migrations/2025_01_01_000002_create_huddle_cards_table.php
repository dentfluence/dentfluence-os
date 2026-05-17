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

            // Card type determines which transformer rendered it
            $table->enum('card_type', [
                'patient_flow',   // from appointments
                'task',           // from tasks
                'comms',          // from CRM / communication
                'lab',            // from lab module
                'inventory',      // from inventory module
                'marketing',      // from marketing module
                'maintenance',    // manual
                'comment',        // staff comment / hurdle
                'quick_action',   // shortcuts
            ]);

            // Polymorphic reference to the source record
            // e.g. source_type = 'appointment', source_id = appointments.id
            // e.g. source_type = 'task',        source_id = tasks.id
            $table->string('source_type')->nullable(); // model class short name
            $table->unsignedBigInteger('source_id')->nullable();

            // Column position on the kanban board
            $table->string('column_key'); // e.g. 'today_flow', 'tasks', 'comms'
            $table->unsignedSmallInteger('position')->default(0);

            // Card display state
            $table->enum('status', [
                'pending',
                'in_progress',
                'done',
                'overdue',
                'blocked',
                'carried_forward',
            ])->default('pending');

            // Snapshot of key display data (denormalised for performance)
            // Full data is always read from source tables via transformer
            $table->json('snapshot')->nullable();

            // Huddle-specific instruction / note on this card
            $table->text('instruction')->nullable();

            // Assignment
            $table->unsignedBigInteger('assigned_to')->nullable(); // FK → users.id

            // Flags
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_carried_forward')->default(false);
            $table->date('carried_from_date')->nullable();

            $table->timestamps();

            // Index for common queries
            $table->index(['huddle_board_id', 'column_key']);
            $table->index(['source_type', 'source_id']);
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('huddle_cards');
    }
};
