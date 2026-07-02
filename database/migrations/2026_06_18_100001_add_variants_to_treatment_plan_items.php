<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            // Stores material options: [{label, price, selected}]
            // Null = no variants (simple fixed-price procedure)
            $table->json('material_variants')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->dropColumn('material_variants');
        });
    }
};
