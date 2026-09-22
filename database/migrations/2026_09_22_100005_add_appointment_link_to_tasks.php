<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a task to the appointment it produced.
 *
 * WHY: a call task that ends in a booking and a call task that ends in nothing
 * look identical on the board once they are closed. The outcome trail records
 * WHAT was said; this column records whether it actually converted. Without it
 * "did our recall calls fill the chair?" has no answer in the database.
 *
 * Nullable by design — most tasks never produce an appointment, and that is
 * not a defect. nullOnDelete, not cascade: deleting an appointment must never
 * delete the task that created it; the task simply loses its link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('appointment_id')
                  ->nullable()
                  ->after('patient_id')
                  ->constrained('appointments')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['appointment_id']);
            $table->dropColumn('appointment_id');
        });
    }
};
