<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab Case Ratings — doctor rates completed lab work per case.
 *
 * One row per lab case (unique on lab_case_id).
 * Each score is 1–5. Scores roll up into LabVendor quality metrics.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_case_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_case_id')->unique()->constrained('lab_cases')->cascadeOnDelete();
            $table->foreignId('lab_vendor_id')->nullable()->constrained('lab_vendors')->nullOnDelete();
            $table->foreignId('rated_by')->nullable()->constrained('users')->nullOnDelete();

            // Clinical scores (1–5)
            $table->unsignedTinyInteger('fit')->nullable();
            $table->unsignedTinyInteger('shade')->nullable();
            $table->unsignedTinyInteger('margins')->nullable();
            $table->unsignedTinyInteger('occlusion')->nullable();
            $table->unsignedTinyInteger('quality')->nullable();       // overall quality

            // Service scores (1–5)
            $table->unsignedTinyInteger('communication')->nullable();
            $table->unsignedTinyInteger('value')->nullable();         // value for money

            // Overall satisfaction (1–5) — single headline number
            $table->unsignedTinyInteger('overall')->nullable();

            $table->text('notes')->nullable();                         // free-text comments

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_case_ratings');
    }
};
