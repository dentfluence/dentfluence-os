<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_treatment_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('treatment_name');
            $table->string('tooth_no', 30)->nullable();
            $table->json('tags')->nullable();

            $table->date('start_date')->nullable();
            $table->date('completion_date')->nullable();
            $table->date('last_followup_date')->nullable();

            $table->enum('status', ['ongoing', 'completed', 'paused', 'cancelled'])->default('ongoing');
            $table->unsignedSmallInteger('media_count')->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['patient_id', 'treatment_name']);
            $table->index(['tooth_no']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_treatment_cases');
    }
};
