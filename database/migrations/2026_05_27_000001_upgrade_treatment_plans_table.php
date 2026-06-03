<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            // Add patient_id for direct patient-level queries
            $table->foreignId('patient_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('patients')
                  ->cascadeOnDelete();

            // Human-readable plan name (e.g. "Treatment Plan A")
            $table->string('plan_name', 100)->nullable()->after('patient_id');

            // Overall plan status
            $table->enum('status', ['pending', 'ongoing', 'completed', 'cancelled'])
                  ->default('pending')
                  ->after('plan_type');

            // Overall discount at plan level
            $table->decimal('overall_disc_pct', 5, 2)->default(0)->after('total');

            // Who created / confirmed it
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index('patient_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('patient_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['plan_name', 'status', 'overall_disc_pct']);
        });
    }
};
