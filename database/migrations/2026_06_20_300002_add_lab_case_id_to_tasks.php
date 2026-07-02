<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add lab_case_id to tasks so auto-generated lab tasks are
 * linked back to the case — enabling the case row to show
 * a "pending task" badge and preventing duplicate task creation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('lab_case_id')
                  ->nullable()
                  ->after('po_id')
                  ->index();

            $table->foreign('lab_case_id')
                  ->references('id')
                  ->on('lab_cases')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['lab_case_id']);
            $table->dropColumn('lab_case_id');
        });
    }
};
