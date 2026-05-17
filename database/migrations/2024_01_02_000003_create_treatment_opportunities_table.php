<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();

            $table->string('type', 100);                // 'implant','aligner','veneers','full_mouth_rehab', etc.
            $table->string('label', 150)->nullable();   // display label override

            $table->enum('status', ['prospect','discussed','quoted','accepted','declined','completed'])->default('prospect');
            $table->enum('priority', ['low','medium','high'])->default('medium');

            $table->date('follow_up_date')->nullable();
            $table->decimal('estimated_value', 10, 2)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_opportunities');
    }
};
