<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_opportunities', function (Blueprint $table) {
            // Optional reason captured when an opportunity is moved to "Declined".
            $table->text('declined_reason')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_opportunities', function (Blueprint $table) {
            $table->dropColumn('declined_reason');
        });
    }
};
