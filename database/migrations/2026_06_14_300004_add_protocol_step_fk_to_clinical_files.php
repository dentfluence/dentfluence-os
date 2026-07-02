<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7F — Add FK constraint: clinical_files.protocol_step_id → documentation_protocol_steps.id
 *
 * Separated from the clinical_files migration because protocol_steps must
 * already exist before this constraint can be added.
 *
 * Run LAST — after 300001, 300002, and 300003 have all been applied.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_files', function (Blueprint $table) {
            $table->foreign('protocol_step_id')
                  ->references('id')
                  ->on('documentation_protocol_steps')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clinical_files', function (Blueprint $table) {
            $table->dropForeign(['protocol_step_id']);
        });
    }
};
