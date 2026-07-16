<?php

namespace App\Http\Controllers;

use App\Models\PatientJourney;
use App\Models\TreatmentOption;
use App\Models\User;
use App\Services\CaseAcceptance\CaseSelectionService;
use App\Services\CaseAcceptance\JourneyAssembler;
use App\Services\CaseAcceptance\JourneySnapshotService;
use App\Services\Relationship\ActivityEngine;
use App\Services\TreatmentPlan\TreatmentPlanAcceptanceService;
use App\Services\TreatmentPlan\TreatmentPlanOpportunitySync;
use App\Support\Features\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PublicCaseController — the ONE unauthenticated surface of the Case Acceptance
 * Engine. Mirrors PublicPresentationController exactly (token guard, view
 * recording, accept/decline/callback + guarded Opportunity handling) but reads
 * the IMMUTABLE sent snapshot so live KB/price edits never change what the
 * patient already sees (frozen §6). Acceptance delegates to the shared
 * TreatmentPlanAcceptanceService — no second acceptance path.
 */
class PublicCaseController extends Controller
{
    private function resolve(string $token): ?PatientJourney
    {
        if (! Feature::enabled('case_acceptance.enabled')) {
            return null;
        }

        $journey = PatientJourney::where('token', $token)->first();

        return $journey && $journey->isValid() ? $journey : null;
    }

    public function show(string $token, JourneyAssembler $assembler): View
    {
        $journey = $this->resolve($token);
        if (! $journey) {
            return view('case-journeys.public.expired');
        }

        $isFirstView = is_null($journey->last_viewed_at);
        $journey->recordView();
        if ($journey->status === 'sent') {
            $journey->update(['status' => 'viewed']);
        }

        if ($isFirstView) {
            app(ActivityEngine::class)->log(
                subject:        $journey,
                event:          'case.opened',
                actor:          null,
                metadata:       ['patient_id' => $journey->patient_id],
                relationshipId: $journey->relationship_id,
                description:    'Patient opened the case journey',
            );

            // Opportunity active on view (frozen §8): quoted = estimate given.
            if ($plan = $journey->treatmentPlan) {
                app(TreatmentPlanOpportunitySync::class)->syncStage($plan, 'quoted', [
                    'source'      => 'case_acceptance',
                    'description' => 'Patient opened the case journey',
                ]);
            }
        }

        // Education + alternatives come from the PINNED snapshot; the dentist's
        // actual plan (headline) is overlaid live so it always reflects the real
        // plan + total, even for journeys sent before this view existed.
        $journey->loadMissing('treatmentPlan.items');
        $dto = $journey->sentSnapshot?->snapshot ?? $assembler->assemble($journey);

        $plan = $journey->treatmentPlan;
        $planTotal = 0.0;
        if ($plan) {
            $planTotal = (float) ($plan->total ?: $plan->items->sum('total'));
            $dto['plan'] = [
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
            ];
        }

        return view('case-journeys.public.show', [
            'journey'  => $journey,
            'dto'      => $dto,
            'token'    => $token,
            'estimate' => $planTotal ?: $this->estimateFromSnapshot($dto, $journey),
        ]);
    }

    /** Record a patient choice; returns the updated pinned-price estimate. */
    public function select(Request $request, string $token, CaseSelectionService $selections): JsonResponse
    {
        $journey = $this->resolve($token);
        if (! $journey) {
            return response()->json(['ok' => false, 'message' => 'This link is no longer active.'], 410);
        }
        if (in_array($journey->status, ['accepted', 'declined'], true)) {
            return response()->json(['ok' => false, 'message' => 'This case is already closed.'], 422);
        }

        $data = $request->validate([
            'node_id'   => ['required', 'integer'],
            'option_id' => ['nullable', 'integer'],
        ]);

        $selections->select($journey, (int) $data['node_id'], $data['option_id'] ?? null);

        app(ActivityEngine::class)->log(
            subject:        $journey,
            event:          'case.material_selected',
            actor:          null,
            metadata:       ['patient_id' => $journey->patient_id, 'node_id' => $data['node_id'], 'option_id' => $data['option_id'] ?? null],
            relationshipId: $journey->relationship_id,
            description:    'Patient made a selection in the case journey',
        );

        $dto = $journey->sentSnapshot?->snapshot ?? [];

        return response()->json([
            'ok'       => true,
            'estimate' => $this->estimateFromSnapshot($dto, $journey->refresh()),
        ]);
    }

    public function accept(string $token, JourneySnapshotService $snapshots, Request $request): RedirectResponse|View
    {
        $journey = $this->resolve($token);
        if (! $journey) {
            return view('case-journeys.public.expired');
        }

        $plan  = $journey->treatmentPlan;
        $actor = $journey->created_by ? User::find($journey->created_by) : null;

        // Shared acceptance orchestration (also syncs opportunity → completed).
        if ($plan) {
            app(TreatmentPlanAcceptanceService::class)->accept(
                $plan, $actor, via: 'case_acceptance', createdBy: $journey->created_by,
            );
        }

        $journey->update(['status' => 'accepted', 'phase' => 'accepted']);

        // Immutable consent snapshot (what the patient confirmed).
        $snapshots->consent($journey, $request->ip(), $request->userAgent());

        app(ActivityEngine::class)->log(
            subject:        $journey,
            event:          'case.accepted',
            actor:          $actor,
            metadata:       ['patient_id' => $journey->patient_id],
            relationshipId: $journey->relationship_id,
            description:    'Patient accepted via Case Acceptance journey',
        );

        return redirect()->route('case.public.show', $token);
    }

    public function decline(string $token): RedirectResponse|View
    {
        $journey = $this->resolve($token);
        if (! $journey) {
            return view('case-journeys.public.expired');
        }

        $journey->update(['status' => 'declined']);

        if ($plan = $journey->treatmentPlan) {
            app(TreatmentPlanOpportunitySync::class)->syncStage($plan, 'declined', [
                'source'          => 'case_acceptance',
                'declined_reason' => 'Declined via Case Acceptance journey',
                'description'     => 'Opportunity declined from case journey',
            ]);
        }

        app(ActivityEngine::class)->log(
            subject:        $journey,
            event:          'case.declined',
            actor:          null,
            metadata:       ['patient_id' => $journey->patient_id],
            relationshipId: $journey->relationship_id,
            description:    'Patient declined via Case Acceptance journey',
        );

        return redirect()->route('case.public.show', $token);
    }

    public function requestCallback(string $token): RedirectResponse|View
    {
        $journey = $this->resolve($token);
        if (! $journey) {
            return view('case-journeys.public.expired');
        }

        if (! in_array($journey->status, ['accepted', 'declined'], true)) {
            $journey->update(['status' => 'follow_up']);
        }

        if ($plan = $journey->treatmentPlan) {
            app(TreatmentPlanOpportunitySync::class)->syncStage($plan, 'discussed', [
                'source'      => 'case_acceptance',
                'priority'    => 'high',
                'description' => 'Patient requested a callback from case journey',
            ]);
        }

        app(ActivityEngine::class)->log(
            subject:        $journey,
            event:          'case.more_time_requested',
            actor:          null,
            metadata:       ['patient_id' => $journey->patient_id],
            relationshipId: $journey->relationship_id,
            description:    'Patient requested more time / a callback via case journey',
        );

        return redirect()->route('case.public.show', $token);
    }

    /**
     * Running estimate from the PINNED snapshot prices (never live pricing, so
     * a sent journey's total can't drift). Sums the price of each selected
     * option as pinned in the snapshot; a selection with no priced option adds
     * nothing.
     */
    private function estimateFromSnapshot(array $dto, PatientJourney $journey): float
    {
        $prices = [];   // option_id => pinned price
        $walk = function ($nodes) use (&$walk, &$prices) {
            foreach ($nodes as $node) {
                foreach ($node['pricing']['options'] ?? [] as $opt) {
                    $prices[(int) $opt['id']] = (float) $opt['price'];
                }
                $walk($node['children'] ?? []);
            }
        };
        $walk($dto['nodes'] ?? []);

        $total = 0.0;
        foreach ($journey->selections as $selection) {
            $optId = $selection->treatment_option_id;
            if ($optId !== null && isset($prices[(int) $optId])) {
                $total += $prices[(int) $optId];
            }
        }

        return round($total, 2);
    }
}
