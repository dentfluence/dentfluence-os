<?php

namespace App\Http\Controllers;

use App\Models\DecisionTree;
use App\Models\JourneyCuration;
use App\Models\PatientJourney;
use App\Models\TreatmentPlan;
use App\Services\CaseAcceptance\JourneyAssembler;
use App\Services\CaseAcceptance\JourneySnapshotService;
use App\Services\Relationship\ActivityEngine;
use App\Services\TreatmentPlan\TreatmentPlanOpportunitySync;
use App\Support\Features\Feature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * CaseJourneyController — staff side of the Case Acceptance Engine. Curate and
 * send a guided case journey from the treatment-plan tab. The whole surface is
 * gated by the `case_acceptance.enabled` feature flag so it coexists with the
 * Smart Presentation module and can be switched off instantly.
 *
 * Reuses (does not rebuild): TreatmentPlanOpportunitySync, ActivityEngine, and
 * the acceptance service (via PublicCaseController). Mirrors the shape of
 * PresentationController::createFromPlan / send.
 */
class CaseJourneyController extends Controller
{
    /**
     * Feature gate. The base Controller (Laravel 11) has no middleware()
     * helper, so the flag is enforced explicitly at the top of every action.
     */
    private function ensureEnabled(): void
    {
        abort_unless(Feature::enabled('case_acceptance.enabled'), 404);
    }

    /** Entry point from the treatment-plan tab. Reuses an existing draft. */
    public function createFromPlan(TreatmentPlan $plan): RedirectResponse
    {
        $this->ensureEnabled();

        $tree = DecisionTree::where('status', 'published')->where('slug', 'missing-tooth')->first()
            ?? DecisionTree::where('status', 'published')->first();

        abort_if($tree === null, 404, 'No published decision tree is available yet.');

        $journey = PatientJourney::firstOrCreate(
            [
                'treatment_plan_id' => $plan->id,
                'status'            => 'draft',
            ],
            [
                'journey_type'    => 'case_acceptance',
                'patient_id'      => $plan->patient_id,
                'relationship_id' => $plan->patient?->relationship_id,
                'decision_tree_id' => $tree->id,
                'delivery_mode'   => 'chairside',
                'cost_visibility' => 'full',
                'created_by'      => Auth::id(),
            ]
        );

        if ($journey->wasRecentlyCreated) {
            app(ActivityEngine::class)->log(
                subject:        $journey,
                event:          'case.created',
                actor:          Auth::user(),
                metadata:       ['patient_id' => $journey->patient_id, 'treatment_plan_id' => $plan->id],
                relationshipId: $plan->patient?->relationship_id,
                description:    'Case journey created from treatment plan',
            );
        }

        return redirect()->route('case-journeys.builder', $journey);
    }

    /** The curate + preview screen. */
    public function builder(PatientJourney $journey, JourneyAssembler $assembler, \App\Services\CaseAcceptance\CasePricingClient $pricing): View
    {
        $this->ensureEnabled();
        $journey->load(['patient', 'treatmentPlan.items', 'decisionTree.nodes.topic', 'decisionTree.nodes.treatment', 'curations', 'customOptions.treatment']);

        $nodes = $journey->decisionTree?->nodes->sortBy('sort_order')->values() ?? collect();

        // Live treatment + price per node, straight from the Treatment Module —
        // so the doctor ticks real, priced options, not abstract nodes.
        $nodePricing = [];
        foreach ($nodes as $node) {
            if ($node->treatment_id) {
                $nodePricing[$node->id] = [
                    'treatment' => $node->treatment?->name,
                    'group'     => $node->treatment_option_group,
                    'price'     => $pricing->priceFor($node),
                ];
            }
        }

        return view('case-journeys.builder', [
            'journey'       => $journey,
            'dto'           => $assembler->assemble($journey),
            'nodes'         => $nodes,
            'curationMap'   => $journey->curations->keyBy('decision_tree_node_id'),
            'nodePricing'   => $nodePricing,
            'customOptions' => $journey->customOptions,
            'treatments'    => \App\Models\Treatment::where('is_active', true)
                                    ->orderBy('name')->get(['id', 'name', 'default_price']),
            'publicUrl'     => $journey->token ? route('case.public.show', $journey->token) : null,
        ]);
    }

    /**
     * Doctor's own preview of the patient microsite — a LIVE render of the
     * current (draft or sent) journey, with no token, no view recording, and
     * no acceptance. Lets the doctor see exactly what the patient will see
     * before sending.
     */
    public function preview(PatientJourney $journey, JourneyAssembler $assembler): View
    {
        $this->ensureEnabled();
        $journey->loadMissing('treatmentPlan.items');
        $dto = $assembler->assemble($journey);

        return view('case-journeys.public.show', [
            'journey'  => $journey,
            'dto'      => $dto,
            'token'    => null,
            'estimate' => $dto['estimate']['total'] ?? 0,
            'preview'  => true,
        ]);
    }

    /** Sync the doctor-added treatment options for this journey (from the Treatment list). */
    public function syncCustomOptions(Request $request, PatientJourney $journey): RedirectResponse
    {
        $this->ensureEnabled();
        abort_if(in_array($journey->status, ['sent', 'viewed', 'accepted'], true), 422,
            'A sent journey is read-only. Edit creates a new revision instead.');

        $data = $request->validate([
            'options'                  => ['array'],
            'options.*.treatment_id'   => ['required', 'integer', 'exists:treatments,id'],
            'options.*.is_recommended' => ['nullable', 'boolean'],
        ]);

        $journey->customOptions()->delete();
        foreach ($data['options'] ?? [] as $i => $row) {
            \App\Models\JourneyCustomOption::create([
                'patient_journey_id' => $journey->id,
                'treatment_id'       => (int) $row['treatment_id'],
                'is_recommended'     => (bool) ($row['is_recommended'] ?? false),
                'sort_order'         => $i,
            ]);
        }

        return redirect()->route('case-journeys.builder', $journey)->with('status', 'Options updated.');
    }

    /** Save per-node doctor curation (visible / recommended / order). */
    public function updateCuration(Request $request, PatientJourney $journey): RedirectResponse
    {
        $this->ensureEnabled();
        abort_if(in_array($journey->status, ['sent', 'viewed', 'accepted'], true), 422,
            'A sent journey is read-only. Edit creates a new revision instead.');

        $data = $request->validate([
            'nodes'                    => ['array'],
            'nodes.*.visible'          => ['nullable', 'boolean'],
            'nodes.*.is_recommended'   => ['nullable', 'boolean'],
            'nodes.*.sort_order'       => ['nullable', 'integer'],
        ]);

        foreach ($data['nodes'] ?? [] as $nodeId => $row) {
            JourneyCuration::updateOrCreate(
                ['patient_journey_id' => $journey->id, 'decision_tree_node_id' => (int) $nodeId],
                [
                    'visible'        => (bool) ($row['visible'] ?? true),
                    'is_recommended' => (bool) ($row['is_recommended'] ?? false),
                    'sort_order'     => (int) ($row['sort_order'] ?? 0),
                ]
            );
        }

        return redirect()->route('case-journeys.builder', $journey)
            ->with('status', 'Curation saved.');
    }

    /**
     * Pin the sent snapshot, issue the token, and put the opportunity into
     * "quoted" (estimate given). A journey that was already sent is superseded
     * first, so a new revision + token is produced (never an in-place edit).
     */
    public function send(Request $request, PatientJourney $journey, JourneySnapshotService $snapshots): RedirectResponse
    {
        $this->ensureEnabled();
        $data = $request->validate([
            'delivery_mode'   => ['required', 'in:chairside,take_home,both'],
            'cost_visibility' => ['required', 'in:full,starting_from,hidden_until_booking'],
        ]);

        if (in_array($journey->status, ['sent', 'viewed'], true)) {
            $journey = $snapshots->supersede($journey);
        }

        $journey->update($data);
        $journey = $snapshots->send($journey);

        // Opportunity goes active on estimate-given (single writer, idempotent).
        if ($plan = $journey->treatmentPlan) {
            app(TreatmentPlanOpportunitySync::class)->syncStage($plan, 'quoted', [
                'source'      => 'case_acceptance',
                'created_by'  => $journey->created_by,
                'description' => 'Estimate given via Case Acceptance journey',
            ]);
        }

        app(ActivityEngine::class)->log(
            subject:        $journey,
            event:          'case.sent',
            actor:          Auth::user(),
            metadata:       ['patient_id' => $journey->patient_id, 'treatment_plan_id' => $journey->treatment_plan_id],
            relationshipId: $journey->relationship_id,
            description:    'Case journey sent to patient',
        );

        return redirect()->route('case-journeys.builder', $journey)
            ->with('status', 'Journey sent. Share the patient link below.');
    }
}
