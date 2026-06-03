<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('appointments', function (Blueprint $table) {
        $table->foreignId('treatment_category_id')
              ->nullable()
              ->after('type')
              ->constrained('treatment_categories')
              ->nullOnDelete();

        $table->foreignId('treatment_id')
              ->nullable()
              ->after('treatment_category_id')
              ->constrained('treatments')
              ->nullOnDelete();
    });
}

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('treatment_category_id');
            $table->dropConstrainedForeignId('treatment_id');
            $table->dropColumn('notes');
        });
    }
};
