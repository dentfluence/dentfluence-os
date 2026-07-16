<?php

namespace App\Services\CaseAcceptance;

use App\Models\DecisionTreeNode;
use App\Models\KbBlock;
use App\Models\PatientJourney;
use App\Services\Presentations\ToothLocationDescriber;

/**
 * JourneyAssembler — walks the decision tree, filters by doctor curation,
 * hydrates education (KB blocks + media) and live pricing (Treatment Module),
 * injects whitelisted tokens, and emits a NORMALIZED BLOCK DTO (structured
 * JSON, never HTML — frozen §7). Web Blade renders it now; Flutter/print reuse
 * the same payload later. Pure read/merge — stores nothing.
 */
class JourneyAssembler
{
    public function __construct(
        private CasePricingClient $pricing,
        private TokenResolver $tokens,
        private ToothLocationDescriber $teeth,
    ) {}

    public function assemble(PatientJourney $journey): array
    {
        $journey->loadMissing([
            'patient',
            'treatmentPlan.items',
            'decisionTree.nodes.topic.blocks.media.asset',
            'curations',
            'selections',
            'customOptions.treatment',
        ]);

        $context     = $this->tokenContext($journey);
        $curationMap = $journey->curations->keyBy('decision_tree_node_id');
        $selectionMap = $journey->selections->keyBy('decision_tree_node_id');

        $nodes = $journey->decisionTree?->nodes ?? collect();
        $roots = $nodes->whereNull('parent_node_id')->sortBy('sort_order');

        $tree = $roots
            ->map(fn (DecisionTreeNode $node) => $this->buildNode($node, $nodes, $curationMap, $selectionMap, $context))
            ->filter()          // curation may hide a whole branch
            ->values()
            ->all();

        // Doctor-added options (from this clinic's Treatment list) render as extra
        // option cards under the root, priced live by the Treatment Module.
        $customCards = $journey->customOptions->map(function ($co) {
            return [
                'id'             => 't' . $co->treatment_id,
                'node_type'      => 'option',
                'label'          => $co->label ?: $co->treatment?->name,
                'treatment_id'   => $co->treatment_id,
                'is_terminal'    => true,
                'is_recommended' => (bool) $co->is_recommended,
                'is_custom'      => true,
                'education'      => null,
                'pricing'        => $this->pricing->forTreatment($co->treatment_id),
                'selected_option_id' => null,
                'children'       => [],
            ];
        })->all();

        if (! empty($customCards) && isset($tree[0])) {
            $tree[0]['children'] = array_merge($tree[0]['children'] ?? [], $customCards);
        }

        $plan      = $journey->treatmentPlan;
        $planTotal = $plan ? (float) ($plan->total ?: $plan->items->sum('total')) : 0.0;

        return [
            'journey' => [
                'id'                 => $journey->id,
                'token'              => $journey->token,
                'status'             => $journey->status,
                'delivery_mode'      => $journey->delivery_mode,
                'cost_visibility'    => $journey->cost_visibility,
                'patient_first_name' => $context['patient_first_name'],
                'tooth_name'         => $context['tooth_name'],
                'tooth_count'        => $context['tooth_count'],
            ],
            'tree' => [
                'slug'  => $journey->decisionTree?->slug,
                'title' => $journey->decisionTree?->title,
            ],
            // The dentist's ACTUAL plan — headlined on the microsite. The tree
            // nodes below are the educational alternatives.
            'plan' => $plan ? [
                'id'    => $plan->id,
                'name'  => $plan->plan_name,
                'total' => $planTotal,
                'items' => $plan->items->map(fn ($it) => [
                    'treatment_name' => $it->treatment_name,
                    'treatment_id'   => $it->treatment_id,
                    'teeth'          => $it->tooth_number,
                    'units'          => (int) $it->units,
                    'total'          => (float) $it->total,
                ])->values()->all(),
            ] : null,
            'nodes'    => $tree,
            'estimate' => [
                'currency' => 'INR',
                // Default to the real plan total; fall back to any live selections.
                'total'    => $planTotal ?: $this->runningEstimate($journey, $selectionMap),
            ],
        ];
    }

    /**
     * Recursively build one node's DTO (education + pricing + children),
     * honouring curation visibility.
     */
    private function buildNode(
        DecisionTreeNode $node,
        $allNodes,
        $curationMap,
        $selectionMap,
        array $context
    ): ?array {
        $curation = $curationMap->get($node->id);

        // Draft (no curation yet) → everything visible. Curated → respect it.
        $visible     = $curation ? (bool) $curation->visible : true;
        $recommended = $curation ? (bool) $curation->is_recommended : false;

        if (! $visible) {
            return null;
        }

        $children = $allNodes
            ->where('parent_node_id', $node->id)
            ->sortBy('sort_order')
            ->map(fn (DecisionTreeNode $child) => $this->buildNode($child, $allNodes, $curationMap, $selectionMap, $context))
            ->filter()
            ->values()
            ->all();

        $selection = $selectionMap->get($node->id);

        return [
            'id'             => $node->id,
            'node_type'      => $node->node_type,
            'label'          => $node->label,
            'treatment_id'   => $node->treatment_id,
            'is_terminal'    => (bool) $node->is_terminal,
            'is_recommended' => $recommended,
            'education'      => $this->education($node, $context),
            'pricing'        => $this->pricing->forNode($node),
            'selected_option_id' => $selection?->treatment_option_id,
            'children'       => $children,
        ];
    }

    /** Hydrate a node's KB topic into resolved education blocks (+ media refs). */
    private function education(DecisionTreeNode $node, array $context): ?array
    {
        $topic = $node->topic;
        if (! $topic) {
            return null;
        }

        $blocks = $topic->blocks
            ->where('locale', 'en')          // V1 single locale
            ->sortBy('sort_order')
            ->map(fn (KbBlock $block) => [
                'block_type' => $block->block_type,
                'title'      => $this->tokens->resolve($block->title, $context),
                'body'       => $this->tokens->resolve($block->body, $context),
                'media'      => $block->media->map(fn ($link) => [
                    'role'       => $link->role,
                    'media_type' => $link->asset?->media_type,
                    'path'       => $link->asset?->path,
                    'mime'       => $link->asset?->mime,
                ])->filter(fn ($m) => $m['path'] !== null)->values()->all(),
            ])
            ->values()
            ->all();

        return [
            'topic'  => ['slug' => $topic->slug, 'title' => $topic->title],
            'blocks' => $blocks,
        ];
    }

    /** Running estimate: sum of the resolved price for every actively-selected node. */
    private function runningEstimate(PatientJourney $journey, $selectionMap): float
    {
        $nodes = ($journey->decisionTree?->nodes ?? collect())->keyBy('id');
        $total = 0.0;

        foreach ($selectionMap as $nodeId => $selection) {
            $node = $nodes->get($nodeId);
            if ($node) {
                $total += $this->pricing->priceFor($node, $selection->treatment_option_id);
            }
        }

        return round($total, 2);
    }

    /** Build the whitelisted token context from the journey's plan + patient. */
    private function tokenContext(PatientJourney $journey): array
    {
        $patient = $journey->patient;
        $first   = $patient?->first_name ?: ($patient?->name ? strtok($patient->name, ' ') : null);

        $toothNumbers = optional($journey->treatmentPlan)->items
            ?->pluck('tooth_number')
            ->filter()
            ->implode(',');

        return [
            'patient_first_name' => $first,
            'tooth_name'         => $toothNumbers ? $this->teeth->phraseFor($toothNumbers) : null,
            'tooth_count'        => $toothNumbers ? count(array_filter(explode(',', $toothNumbers))) : null,
        ];
    }
}
