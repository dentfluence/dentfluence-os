<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Earnings
            $table->decimal('basic_salary', 10, 2)->default(0);
            $table->decimal('hra', 10, 2)->default(0);          // House Rent Allowance
            $table->decimal('conveyance', 10, 2)->default(0);   // Transport
            $table->decimal('medical', 10, 2)->default(0);      // Medical allowance
            $table->decimal('special', 10, 2)->default(0);      // Special/other allowance

            // Deductions (statutory)
            $table->boolean('pf_applicable')->default(false);
            $table->boolean('esi_applicable')->default(false);
            $table->decimal('professional_tax', 10, 2)->default(200); // ₹200/month Maharashtra

            // Overtime config
            $table->decimal('ot_multiplier', 4, 2)->default(1.50); // 1.5x or 2x

            $table->timestamps();

            $table->unique('user_id'); // One salary structure per staff member
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_salary_components');
    }
};
