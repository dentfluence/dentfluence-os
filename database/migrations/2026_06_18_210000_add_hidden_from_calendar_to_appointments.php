<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Soft-hides a cancelled appointment from the calendar without deleting it
            $table->boolean('hidden_from_calendar')->default(false)->after('cancel_reason');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('hidden_from_calendar');
        });
    }
};
