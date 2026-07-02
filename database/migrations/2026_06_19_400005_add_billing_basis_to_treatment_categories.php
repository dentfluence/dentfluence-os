<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_categories', function (Blueprint $table) {
            // gross       = doctor incentive on gross billed amount (regular treatments)
            // net_collected = incentive on what's actually collected (phased: ortho/implants/FMR)
            $table->enum('billing_basis', ['gross', 'net_collected'])
                  ->default('gross')
                  ->after('description');
            $table->boolean('is_phased')->default(false)->after('billing_basis'); // Ortho/Implant/FMR flag
        });
    }

    public function down(): void
    {
        Schema::table('treatment_categories', function (Blueprint $table) {
            $table->dropColumn(['billing_basis', 'is_phased']);
        });
    }
};
