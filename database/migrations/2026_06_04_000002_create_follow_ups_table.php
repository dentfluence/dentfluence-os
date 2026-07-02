<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();

            // Who this follow-up is for (patient OR lead — one will be null)
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->unsignedBigInteger('lead_id')->nullable(); // FK added when Lead module is built

            // What and when
            $table->string('label');                            // e.g. "Post-Op Day 1 Review"
            $table->string('trigger_type')->nullable();         // treatment_status_changed | prm_stage_changed | appointment_event | manual | special_occasion
            $table->string('trigger_value')->nullable();        // e.g. "extraction", "new_lead", "missed"
            $table->date('due_date');
            $table->string('due_time', 10)->default('10:00');   // HH:MM

            // How
            $table->string('channel', 30)->default('call');     // call | whatsapp | clinic_visit | any
            $table->string('priority', 10)->default('medium');  // high | medium | low

            // Status
            $table->string('status', 20)->default('pending');   // pending | completed | rescheduled | cancelled

            // Additional info
            $table->text('note')->nullable();
            $table->json('appears_in')->nullable();             // ['communication_manager','daily_huddle']
            $table->boolean('auto_created')->default(false);

            // Assignment
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // Completion tracking
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('completion_note')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Useful indexes
            $table->index(['status', 'due_date']);
            $table->index(['patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
    }
};
