<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            // Optional, informal alternative to an exact follow_up_date — e.g. "Review after 5 days".
            // Pure note for the printed Rx; does NOT create an appointment or reminder.
            $table->unsignedSmallInteger('follow_up_after_days')->nullable()->after('follow_up_date');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn('follow_up_after_days');
        });
    }
};
