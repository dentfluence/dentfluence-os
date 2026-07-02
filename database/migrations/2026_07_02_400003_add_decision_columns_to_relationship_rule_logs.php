<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0 — Decision Log foundation.
 *
 * Additively extends relationship_rule_logs into the full "Decision Log"
 * described in the Blueprint (§4): every automation decision becomes
 * explainable — "why wasn't this patient contacted?".
 *
 * ALL new columns are nullable. Existing rows and existing writers are
 * unaffected. RulesEngine is NOT modified in Phase 0 — this only prepares the
 * schema + writer (DecisionLogRecorder) for Phase 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('relationship_rule_logs')) {
            return; // table arrives with the Relationship Engine; guard defensively
        }

        Schema::table('relationship_rule_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('relationship_rule_logs', 'inputs')) {
                $table->json('inputs')->nullable()->after('metadata');
            }
            if (! Schema::hasColumn('relationship_rule_logs', 'conditions')) {
                $table->json('conditions')->nullable()->after('inputs');
            }
            if (! Schema::hasColumn('relationship_rule_logs', 'result')) {
                $table->string('result')->nullable()->after('conditions');
            }
            if (! Schema::hasColumn('relationship_rule_logs', 'decision')) {
                $table->string('decision')->nullable()->after('result');
            }
            if (! Schema::hasColumn('relationship_rule_logs', 'requesting_engine')) {
                $table->string('requesting_engine')->nullable()->after('decision');
            }
            if (! Schema::hasColumn('relationship_rule_logs', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('requesting_engine');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('relationship_rule_logs')) {
            return;
        }

        Schema::table('relationship_rule_logs', function (Blueprint $table) {
            foreach (['inputs', 'conditions', 'result', 'decision', 'requesting_engine', 'user_id'] as $col) {
                if (Schema::hasColumn('relationship_rule_logs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
