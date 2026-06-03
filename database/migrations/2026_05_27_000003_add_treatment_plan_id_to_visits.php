<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_visits', function (Blueprint $table) {
            $table->foreignId('treatment_plan_id')
                  ->nullable()
                  ->after('consultation_id')
                  ->constrained('treatment_plans')
                  ->nullOnDelete();

            $table->index('treatment_plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('treatment_plan_id');
        });
    }
};
