<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add fields needed for the Treatment Plan print document:
     *  - estimated_duration : e.g. "3–4 Months"
     *  - visit_count        : approximate number of visits
     *  - doctor_notes       : optional recommendation by the doctor
     */
    public function up(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->string('estimated_duration', 50)->nullable()->after('plan_name');
            $table->unsignedTinyInteger('visit_count')->nullable()->after('estimated_duration');
            $table->text('doctor_notes')->nullable()->after('overall_disc_pct');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->dropColumn(['estimated_duration', 'visit_count', 'doctor_notes']);
        });
    }
};
