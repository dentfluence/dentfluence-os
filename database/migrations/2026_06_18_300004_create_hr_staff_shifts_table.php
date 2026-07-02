<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR Staff Shifts — assigns a shift to each staff member.
     * effective_from / effective_to allows shift changes over time.
     */
    public function up(): void
    {
        Schema::create('hr_staff_shifts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->foreignId('shift_id')
                  ->constrained('hr_shifts')
                  ->cascadeOnDelete();

            $table->date('effective_from');
            $table->date('effective_to')->nullable();       // null = currently active

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_staff_shifts');
    }
};
