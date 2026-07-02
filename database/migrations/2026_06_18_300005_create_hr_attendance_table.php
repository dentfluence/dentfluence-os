<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR Attendance — one record per staff per day.
     * check_in_method: 'qr' (Android app scan) | 'manual' (admin override)
     * marked_by: user_id of admin if manually marked
     */
    public function up(): void
    {
        Schema::create('hr_attendance', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->date('date');

            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();

            $table->enum('status', [
                'present',
                'absent',
                'late',          // checked in after shift start grace period
                'half_day',
                'on_leave',
                'holiday',
            ])->default('absent');

            $table->enum('check_in_method', ['qr', 'manual'])->nullable();
            $table->enum('check_out_method', ['qr', 'manual'])->nullable();

            // Admin who manually marked/overrode this record
            $table->foreignId('marked_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            // One record per staff per day
            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance');
    }
};
