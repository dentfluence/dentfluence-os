<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Explicit plan date for the treatment plan — printed as the "Date" on the
     * plan and used as the base for the validity window. Nullable: older plans
     * (and any left blank) fall back to the consultation date, then today,
     * at print time — preserving previous behaviour.
     */
    public function up(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->date('plan_date')
                  ->nullable()
                  ->after('doctor_id');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->dropColumn('plan_date');
        });
    }
};
