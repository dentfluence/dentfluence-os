<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link tasks back to the practice protocol that generated them, and
     * carry the "proof required" flag onto the task instance.
     *
     * Both columns are additive and nullable/defaulted — every existing task
     * is unaffected (practice_protocol_id stays null for hand-made tasks).
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('practice_protocol_id')
                  ->nullable()
                  ->after('lab_case_id')
                  ->constrained('practice_protocols')
                  ->nullOnDelete();

            $table->boolean('requires_evidence')
                  ->default(false)
                  ->after('practice_protocol_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['practice_protocol_id']);
            $table->dropColumn(['practice_protocol_id', 'requires_evidence']);
        });
    }
};
