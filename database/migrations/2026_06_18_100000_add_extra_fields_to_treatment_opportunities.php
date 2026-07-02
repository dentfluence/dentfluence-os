<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_opportunities', function (Blueprint $table) {
            // Staff member responsible for following up on this opportunity
            $table->foreignId('assigned_to')
                  ->nullable()
                  ->after('created_by')
                  ->constrained('users')
                  ->nullOnDelete();

            // Time component for the follow-up (e.g. 11:00)
            $table->time('follow_up_time')->nullable()->after('follow_up_date');

            // Link to the treatment plan that originated this opportunity (optional)
            $table->foreignId('treatment_plan_id')
                  ->nullable()
                  ->after('patient_id')
                  ->constrained('treatment_plans')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('treatment_opportunities', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['treatment_plan_id']);
            $table->dropColumn(['assigned_to', 'follow_up_time', 'treatment_plan_id']);
        });
    }
};
