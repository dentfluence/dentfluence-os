<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds a JSON `stages` column to the treatments table.
     * Stores an ordered array of stage objects: [{"key":"stage_key","label":"Stage Label"}, ...]
     * Defined per-treatment in the Treatment module (Stages tab).
     * Used by the visit form to drive stage progress tracking.
     */
    public function up(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->json('stages')->nullable()->after('description')
                  ->comment('Ordered array of {key, label} objects defining visit stages for this treatment');
        });
    }

    public function down(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->dropColumn('stages');
        });
    }
};
