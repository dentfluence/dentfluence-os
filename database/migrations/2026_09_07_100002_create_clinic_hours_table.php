<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clinic working hours — when the clinic is open, per branch, per weekday.
 *
 * TWO SESSIONS PER DAY, not one open-to-close. An Indian dental clinic runs a
 * morning session and an evening session with an afternoon break, and a single
 * open/close pair would either book patients into the break or force the clinic
 * to describe its day dishonestly. Both competitors that store hours at all
 * store them this way.
 *
 * Session 2 is nullable: a clinic that runs straight through fills only
 * session 1, and a closed day sets is_closed instead of blanking the times, so
 * "we are shut on Sunday" and "nobody has configured Sunday yet" stay
 * different facts.
 *
 * NOTHING is seeded. With no rows the clinic has no configured hours and the
 * app behaves exactly as it does today — see ClinicHoursService::isConfigured().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');

            // 0 = Sunday … 6 = Saturday, matching Carbon::dayOfWeek so no
            // translation table is ever needed between PHP and this column.
            $table->unsignedTinyInteger('weekday');

            $table->boolean('is_closed')->default(false);

            $table->time('slot1_start')->nullable();
            $table->time('slot1_end')->nullable();
            $table->time('slot2_start')->nullable();
            $table->time('slot2_end')->nullable();

            $table->timestamps();

            $table->unique(['branch_id', 'weekday']);
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_hours');
    }
};
