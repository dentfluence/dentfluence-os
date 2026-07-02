<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR Shifts — Morning, Evening, Full Day etc.
     */
    public function up(): void
    {
        Schema::create('hr_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');                        // e.g. "Morning"
            $table->time('start_time');                    // e.g. 09:00
            $table->time('end_time');                      // e.g. 14:00
            $table->unsignedTinyInteger('branch_id')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_shifts');
    }
};
