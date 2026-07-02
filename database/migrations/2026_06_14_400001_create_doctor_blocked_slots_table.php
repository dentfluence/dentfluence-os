<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_blocked_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
            $table->date('block_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('reason')->nullable(); // e.g. "Out of clinic", "Lunch break", "Training"
            $table->string('block_type')->default('unavailable'); // unavailable | break | emergency
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Index for fast calendar lookup
            $table->index(['doctor_id', 'block_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_blocked_slots');
    }
};
