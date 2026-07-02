<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR Staff Profiles — extends the users table with HR-specific fields.
     * Basic info (name, phone, role, designation, avatar) stays on users.
     * This table holds: joining info, personal details, salary type,
     * emergency contact, and the QR token for Android app check-in.
     */
    public function up(): void
    {
        Schema::create('hr_staff_profiles', function (Blueprint $table) {
            $table->id();

            // Link to users table
            $table->foreignId('user_id')
                  ->unique()
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Department
            $table->foreignId('department_id')
                  ->nullable()
                  ->constrained('hr_departments')
                  ->nullOnDelete();

            // Employee info
            $table->string('employee_code')->unique()->nullable(); // e.g. DF-001
            $table->date('joining_date')->nullable();
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern'])
                  ->default('full_time');

            // Personal details
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('blood_group', 5)->nullable();          // e.g. B+
            $table->text('address')->nullable();

            // Emergency contact
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relation')->nullable(); // e.g. "Spouse"

            // Professional (for doctors)
            $table->string('license_number')->nullable();          // Dental council reg no.
            $table->date('license_expiry')->nullable();            // Triggers alert before expiry
            $table->string('qualification')->nullable();           // BDS, MDS, etc.
            $table->string('specialization')->nullable();          // Orthodontics, Endodontics…

            // Salary (inputs only — not full payroll)
            $table->enum('salary_type', ['fixed', 'hourly'])->default('fixed');
            $table->decimal('basic_salary', 10, 2)->nullable();

            // QR token for Android app attendance check-in
            // Staff show this QR; Android app scans it to log attendance
            $table->string('qr_token')->unique()->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_staff_profiles');
    }
};
