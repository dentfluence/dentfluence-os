<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Defines a standing requirement e.g. "BLS Certification — every 2 years — all clinical staff"
        Schema::create('hr_periodic_training_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('name');                         // e.g. "BLS Renewal", "Fire Safety"
            $table->text('description')->nullable();
            $table->string('applies_to')->nullable();       // e.g. "all", "clinical", specific role
            $table->integer('frequency_months');            // e.g. 12 = yearly, 24 = every 2 years
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // Per-staff compliance record for each requirement
        Schema::create('hr_periodic_training_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_id')
                  ->constrained('hr_periodic_training_requirements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('completed_on');               // date staff completed this requirement
            $table->date('next_due_on');                // computed: completed_on + frequency_months
            $table->foreignId('training_session_id')->nullable()
                  ->constrained('hr_training_sessions')->nullOnDelete(); // link to session if applicable
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_periodic_training_records');
        Schema::dropIfExists('hr_periodic_training_requirements');
    }
};
