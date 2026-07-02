<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('bonus_name', 255);                   // e.g. "Diwali Bonus"
            $table->enum('bonus_type', [
                'festival', 'performance', 'annual', 'joining', 'retention', 'other'
            ])->default('other');
            $table->decimal('amount', 10, 2);
            $table->date('bonus_date');
            $table->string('month_year', 7)->nullable();         // e.g. "2024-10" for payroll month
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_bonuses');
    }
};
