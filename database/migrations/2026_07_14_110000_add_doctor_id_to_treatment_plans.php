<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Treating doctor for the plan — printed on the treatment plan letterhead
     * and signature. Nullable: older plans fall back to the consultation's
     * doctor at print time.
     */
    public function up(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->foreignId('doctor_id')
                  ->nullable()
                  ->after('consultation_id')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('doctor_id');
        });
    }
};
