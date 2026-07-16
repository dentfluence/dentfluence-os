<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Case Acceptance Engine — Milestone 5 implementation-conflict fix.
 *
 * The frozen decision_tree_nodes schema (§5.3) carries `treatment_option_group`
 * but no `treatment_id`, yet the Treatment Module pricing API (§4.1) requires
 * BOTH treatment_id AND group to return prices. Without a treatment binding, a
 * node offering (say) `crown_material` cannot say WHICH treatment's crown
 * options to price. Smallest non-redesign fix: a nullable `treatment_id` FK so
 * option/material/addon nodes bind to their Treatment. Education-only nodes
 * leave it null. Purely additive; changes no frozen concept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decision_tree_nodes', function (Blueprint $table) {
            $table->foreignId('treatment_id')->nullable()->after('kb_topic_id')
                  ->constrained('treatments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('decision_tree_nodes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('treatment_id');
        });
    }
};
