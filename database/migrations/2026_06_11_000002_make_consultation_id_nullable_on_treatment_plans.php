<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v1 required every treatment plan to belong to a consultation.
     * v2 supports patient-level plans (consultation optional),
     * so relax the NOT NULL constraint. The foreign key stays intact —
     * when a value IS provided it must still be a valid consultation.
     */
    public function up(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('consultation_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('consultation_id')->nullable(false)->change();
        });
    }
};
