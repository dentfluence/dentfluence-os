<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The legacy `rows` JSON column was NOT NULL with no default.
     * The v2 module stores line items in `treatment_plan_items` instead,
     * so new plans never send `rows` — make it nullable to fix
     * "Field 'rows' doesn't have a default value" on insert.
     */
    public function up(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->json('rows')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->json('rows')->nullable(false)->change();
        });
    }
};
