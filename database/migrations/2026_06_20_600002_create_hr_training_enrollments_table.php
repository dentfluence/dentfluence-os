<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tracks which staff are enrolled in each training session
        Schema::create('hr_training_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')
                  ->constrained('hr_training_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('attendance', ['pending', 'present', 'absent'])->default('pending');
            $table->boolean('completed')->default(false);
            $table->date('completed_at')->nullable();
            $table->text('feedback')->nullable();    // staff's own feedback / notes
            $table->timestamps();

            $table->unique(['training_session_id', 'user_id']); // no duplicate enrollments
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_training_enrollments');
    }
};
