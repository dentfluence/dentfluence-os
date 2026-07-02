<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global table — no clinic_id, no soft deletes
        Schema::create('mkt_festival_dates', function (Blueprint $table) {
            $table->id();

            $table->string('name');                // "World Oral Health Day"
            $table->string('local_name')->nullable(); // Hindi/regional name if applicable

            $table->enum('category', ['dental', 'national', 'regional', 'religious'])
                  ->default('national');

            // Fixed dates: store month + day (year irrelevant for recurring)
            $table->unsignedTinyInteger('month')->nullable();   // 1-12
            $table->unsignedTinyInteger('day')->nullable();     // 1-31

            // For floating dates (Diwali, Eid, etc.) store actual dates per year
            // If is_recurring = false OR floating, use festival_date
            $table->date('festival_date')->nullable();

            // True = repeats every year (use month+day), False = one-off
            $table->boolean('is_recurring')->default(true);

            // For "nth weekday of month" rules e.g. "1st Friday of October"
            $table->unsignedTinyInteger('nth_week')->nullable();  // 1-5
            $table->unsignedTinyInteger('day_of_week')->nullable(); // 0=Sun, 1=Mon...

            $table->text('description')->nullable();
            $table->string('suggested_content_type', 50)->nullable(); // hint for AI
            $table->json('suggested_hashtags')->nullable();

            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['month', 'day']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_festival_dates');
    }
};
