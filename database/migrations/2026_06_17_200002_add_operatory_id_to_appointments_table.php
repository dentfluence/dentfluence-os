<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds nullable operatory_id to appointments.
 * All existing appointments remain valid — the field is optional.
 * Clinics that don't use operatories are completely unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Nullable so all existing appointments continue to work
            $table->unsignedBigInteger('operatory_id')
                  ->nullable()
                  ->after('chair_number');

            $table->foreign('operatory_id')
                  ->references('id')
                  ->on('operatories')
                  ->nullOnDelete(); // If an operatory is deleted, just clear the field
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['operatory_id']);
            $table->dropColumn('operatory_id');
        });
    }
};
