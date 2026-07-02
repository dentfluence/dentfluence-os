<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_performance_memos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_user_id')          // the staff member memo is about
                  ->constrained('users')->cascadeOnDelete();
            $table->foreignId('issued_by')              // HR / manager who issued it
                  ->constrained('users');
            $table->enum('type', ['praise', 'warning', 'improvement', 'review', 'general'])
                  ->default('general');
            $table->string('subject');
            $table->text('body');
            $table->date('memo_date');
            $table->boolean('staff_acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->boolean('is_confidential')->default(false);  // only HR/admin can see if true
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_performance_memos');
    }
};
