<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Case Acceptance Engine — decision tree nodes (frozen §5.3).
 * Nodes store POINTERS ONLY — never prices, never prose. Education comes from
 * `kb_topic_id`; priced choices come from `treatment_option_group` (a
 * Treatment Module group). `conditions` is reserved — evaluated by a trivial
 * equality matcher, NOT a rule engine (decision #5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_tree_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('decision_tree_id')->constrained('decision_trees')->cascadeOnDelete();
            $table->foreignId('parent_node_id')->nullable()
                  ->constrained('decision_tree_nodes')->cascadeOnDelete();
            $table->enum('node_type', ['consequence', 'option', 'material', 'addon', 'summary']);
            $table->foreignId('kb_topic_id')->nullable()
                  ->constrained('kb_topics')->nullOnDelete();
            $table->string('treatment_option_group', 50)->nullable();   // maps to treatment_options.group
            $table->json('conditions')->nullable();   // reserved — equality matcher only
            $table->string('label')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_terminal')->default(false);
            $table->timestamps();

            $table->index(['decision_tree_id', 'parent_node_id'], 'dtn_tree_parent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_tree_nodes');
    }
};
