<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            // Placed after sub_type_id for logical grouping
            $table->foreignId('variant_id')
                  ->nullable()
                  ->after('sub_type_id')
                  ->constrained('inventory_variants')
                  ->nullOnDelete(); // preserves items when variant is deleted
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropForeign(['variant_id']);
            $table->dropColumn('variant_id');
        });
    }
};
