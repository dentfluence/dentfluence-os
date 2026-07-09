<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Smart Treatment Presentation" — a new, independent module.
     * Owns the communication layer between a Treatment Plan being created and
     * the patient accepting it. Never a second source of truth for clinical
     * data — see docs/plan-smart-treatment-presentation.md.
     */
    public function up(): void
    {
        Schema::create('presentations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Read-only links into the modules this presentation is built from.
            $table->foreignId('treatment_plan_id')->constrained('treatment_plans');
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();

            // Lifecycle — only 'draft' and 'finalized' are used by Slice A+B.
            // sent/viewed/accepted/declined/follow_up_required belong to Slice C+D.
            $table->string('status')->default('draft');

            // Author-owned content (dentist only — see PresentationController).
            $table->text('ai_summary_text')->nullable();
            $table->text('doctor_message')->nullable();

            // Hard review gate — must be set before status can move past 'finalized'.
            $table->timestamp('reviewed_at')->nullable();

            // Slice C/D fields, reserved now so later migrations only ADD columns
            // elsewhere rather than altering this core table.
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'status']);
            $table->index(['treatment_plan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentations');
    }
};
