<?php

namespace Database\Seeders;

use App\Models\DecisionTree;
use App\Models\DecisionTreeNode;
use App\Models\KbTopic;
use App\Models\Treatment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * MissingToothTreeSeeder — Case Acceptance Engine, Milestone 5.
 * See docs/plan-case-acceptance-engine.md §5.3 / §11.
 *
 * Builds the ONE V1 decision tree:
 *   Missing Tooth (education)
 *     ├─ Dental Implant   → implant system + crown material
 *     ├─ Dental Bridge    → crown material
 *     └─ Removable Denture (terminal)
 *
 * Nodes store POINTERS ONLY: `kb_topic_id` for education, `treatment_id` +
 * `treatment_option_group` for live pricing (never prices or prose here).
 * Treatments are resolved by name and bound where the clinic has them; if a
 * treatment is missing the node still stands as education (treatment_id null),
 * so the tree never breaks.
 *
 * IDEMPOTENT: the tree is keyed on slug; its nodes are rebuilt on each run.
 *
 * Run AFTER KnowledgeBankSeeder:
 *   php artisan db:seed --class=MissingToothTreeSeeder
 */
class MissingToothTreeSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $tree = DecisionTree::updateOrCreate(
                ['slug' => 'missing-tooth'],
                [
                    'title'           => 'Missing Tooth Replacement',
                    'entry_condition' => 'missing_tooth',
                    'version'         => '1.0.0',
                    'status'          => 'published',
                ]
            );

            // Rebuild nodes cleanly (small authored tree — safe to recreate).
            DecisionTreeNode::where('decision_tree_id', $tree->id)->delete();

            $topic = fn (string $slug) => optional(KbTopic::where('slug', $slug)->first())->id;
            $treatment = fn (string $needle) => optional(
                Treatment::where('is_active', true)->where('name', 'like', "%{$needle}%")->first()
            )->id;

            // ── Root: the condition education ────────────────────────────
            $root = $this->node($tree->id, null, [
                'node_type'  => 'consequence',
                'kb_topic_id' => $topic('missing-tooth'),
                'label'      => 'Replacing a missing tooth',
                'sort_order' => 0,
                'is_terminal' => false,
            ]);

            // ── Option 1: Dental Implant (implant system + crown) ────────
            $implant = $this->node($tree->id, $root->id, [
                'node_type'              => 'option',
                'kb_topic_id'            => $topic('dental-implant'),
                'treatment_id'           => $treatment('implant'),
                'treatment_option_group' => 'implant_system',
                'label'                  => 'Dental Implant',
                'sort_order'             => 0,
                'is_terminal'            => false,
            ]);
            $this->node($tree->id, $implant->id, [
                'node_type'              => 'material',
                'treatment_id'           => $treatment('implant'),
                'treatment_option_group' => 'crown_material',
                'label'                  => 'Crown material',
                'sort_order'             => 0,
                'is_terminal'            => true,
            ]);

            // ── Option 2: Dental Bridge (crown material) ─────────────────
            $bridge = $this->node($tree->id, $root->id, [
                'node_type'   => 'option',
                'kb_topic_id' => $topic('dental-bridge'),
                'treatment_id' => $treatment('bridge'),
                'label'       => 'Dental Bridge',
                'sort_order'  => 1,
                'is_terminal' => false,
            ]);
            $this->node($tree->id, $bridge->id, [
                'node_type'              => 'material',
                'treatment_id'           => $treatment('bridge'),
                'treatment_option_group' => 'crown_material',
                'label'                  => 'Crown material',
                'sort_order'             => 0,
                'is_terminal'            => true,
            ]);

            // ── Option 3: Removable Partial Denture (terminal) ───────────
            $this->node($tree->id, $root->id, [
                'node_type'    => 'option',
                'kb_topic_id'  => $topic('removable-partial-denture'),
                'treatment_id' => $treatment('denture'),
                'label'        => 'Removable Partial Denture',
                'sort_order'   => 2,
                'is_terminal'  => true,
            ]);
        });
    }

    private function node(int $treeId, ?int $parentId, array $attrs): DecisionTreeNode
    {
        return DecisionTreeNode::create(array_merge([
            'decision_tree_id'       => $treeId,
            'parent_node_id'         => $parentId,
            'kb_topic_id'            => null,
            'treatment_id'           => null,
            'treatment_option_group' => null,
            'conditions'             => null,
        ], $attrs));
    }
}
