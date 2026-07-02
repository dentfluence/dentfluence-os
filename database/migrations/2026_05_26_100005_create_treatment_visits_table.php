<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Base treatment_visits table.
     * Clinical detail columns are added by subsequent migrations (100006, 100007).
     */
    public function up(): void
    {
        Schema::create('treatment_visits', function (Blueprint $table) {
            $table->id();

            // Core relationships
            $table->foreignId('patient_id')
                  ->constrained('patients')
                  ->cascadeOnDelete();

            $table->foreignId('appointment_id')
                  ->nullable()
                  ->constrained('appointments')
                  ->nullOnDelete();

            $table->foreignId('consultation_id')
                  ->nullable()
                  ->constrained('consultations')
                  ->nullOnDelete();

            $table->foreignId('doctor_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // Visit details
            $table->date('visit_date');
            $table->string('procedure')->nullable();         // e.g. RCT, Scaling, Implant
            $table->string('tooth_number')->nullable();      // e.g. 26, 11, UL1
            $table->enum('status', [
                'started',
                'ongoing',
                'completed',
                'abandoned',
            ])->default('started');

            $table->integer('visit_number')->default(1);    // visit 1 of N in a course
            $table->text('clinical_notes')->nullable();
            $table->text('next_visit_plan')->nullable();

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('patient_id');
            $table->index('appointment_id');
            $table->index('visit_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_visits');
    }
};
