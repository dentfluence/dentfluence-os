<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clinic holidays — dated closures that override the weekly pattern.
 *
 * `recurs_annually` is for the fixed-date festivals and civic holidays a clinic
 * closes for every year (26 Jan, 15 Aug, 2 Oct). It is deliberately NOT a rule
 * engine: Diwali and Gudi Padwa move every year on the Gregorian calendar and
 * must be entered per year. Pretending otherwise would close the clinic on the
 * wrong day, which is worse than typing two dates.
 *
 * A null branch_id means "every branch" — useful the day there is a second
 * clinic, harmless today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->date('holiday_date');
            $table->string('name', 120);
            $table->boolean('recurs_annually')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'holiday_date']);
            $table->index('holiday_date');

            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_holidays');
    }
};
