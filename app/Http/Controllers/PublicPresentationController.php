<?php

namespace App\Http\Controllers;

use App\Models\EmiProvider;
use App\Models\Presentation;
use App\Models\PresentationAccessToken;
use App\Models\TreatmentOpportunity;
use App\Models\User;
use App\Services\MembershipBenefitService;
use App\Services\Presentations\PresentationNarrativeService;
use App\Services\Relationship\ActivityEngine;
use App\Services\TreatmentPlan\TreatmentPlanAcceptanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * PublicPresentationController — the ONE unauthenticated surface in this
 * module. A patient opens a token-based link (no login — mirrors the
 * existing "QR check-in, token-based, no login" pattern already used for
 * HR attendance, see routes/web.php) and can view, accept, or decline.
 *
 * Accept() mirrors TreatmentPlanController::accept()'s exact effect
 * (accepted_at/status + the same ActivityEngine event + guarded Opportunity
 * creation) rather than inventing a second acceptance path — but is
 * deliberately duplicated here, not extracted into a shared service, so this
 * change never touches the existing TreatmentPlanController file. Worth a
 * follow-up refactor into a shared service later if the duplication drifts.
 */
class PublicPresentationController extends Controller
{
    public function show(string $token): View
    {
        $accessToken = PresentationAccessToken::where('token', $token)->first();

        if (! $accessToken || ! $accessToken->isValid()) {
            return view('presentations.public.expired');
        }

        $presentation = $accessToken->presentation()->with([
            'patient', 'consultation', 'treatmentPlan.items', 'mediaItems.treatmentMedia',
        ])->first();

        if (! $presentation) {
            return view('presentations.public.expired');
        }

        $accessToken->recordView();

        $isFirstView = is_null($presentation->first_viewed_at);
        $presentation->forceFill([
            'first_viewed_at' => $presentation->first_viewed_at ?? now(),
            'last_viewed_at'  => now(),
            'view_count'      => $presentation->view_count + 1,
            'status'          => $presentation->status === Presentation::STATUS_SENT ? Presentation::STATUS_VIEWED : $presentation->status,
        ])->save();

        if ($isFirstView) {
            app(ActivityEngine::class)->log(
                subject:        $presentation,
                event:          'presentation.viewed',
                actor:          null,
                metadata:       ['patient_id' => $presentation->patient_id],
                relationshipId: $presentation->patient?->relationship_id,
                description:    'Patient opened the smart presentation',
            );
        }

        $costSummary = $presentation->currentCostSummary();

        // ── Phase 3: membership + EMI, pure display wiring onto data that
        // already exists — no new computation, same methods the staff
        // billing panel already calls (MembershipBenefitService::getActive(),
        // EmiScheme::breakdown()). ──
        $activeMembership = $presentation->patient_id
            ? MembershipBenefitService::getActive($presentation->patient_id)
            : null;

        $emiOptions = $costSummary['balance_due'] > 0
            ? EmiProvider::allActive()
                ->flatMap(fn (EmiProvider $provider) => $provider->activeSchemes->map(
                    fn ($scheme) => array_merge($scheme->breakdown($costSummary['balance_due']), [
                        'provider_name' => $provider->name,
                    ])
                ))
                ->values()
            : collect();

        return view('presentations.public.show', [
            'presentation'     => $presentation,
            'costSummary'      => $costSummary,
            'includedMedia'    => $presentation->mediaItems->where('included', true)->pluck('treatmentMedia')->filter(),
            'narrative'        => app(PresentationNarrativeService::class)->build($presentation),
            'token'            => $token,
            'activeMembership' => $activeMembership,
            'emiOptions'       => $emiOptions,
        ]);
    }

    public function accept(string $token): RedirectResponse|View
    {
        $accessToken = PresentationAccessToken::where('token', $token)->first();
        if (! $accessToken || ! $accessToken->isValid()) {
            return view('presentations.public.expired');
        }

        $presentation = $accessToken->presentation;
        $plan = $presentation->treatmentPlan;
        $relationshipId = $presentation->patient?->relationship_id;
        $actor = $presentation->created_by ? User::find($presentation->created_by) : null;

        // Acceptance orchestration is shared with the in-clinic and mobile
        // paths (TreatmentPlanAcceptanceService) — it was previously a
        // hand-copied clone of TreatmentPlanController::accept(), which meant
        // any change had to be made in three places or the channels drifted.
        app(TreatmentPlanAcceptanceService::class)->accept(
            $plan,
            $actor,
            via: 'smart_presentation',
            createdBy: $presentation->created_by,
        );

        $presentation->update(['status' => Presentation::STATUS_ACCEPTED]);

        app(ActivityEngine::class)->log(
            subject:        $presentation,
            event:          'presentation.accepted',
            actor:          $actor,
            metadata:       ['patient_id' => $presentation->patient_id],
            relationshipId: $relationshipId,
            description:    'Patient accepted via Smart Presentation',
        );

        return redirect()->route('presentations.public.show', $token);
    }

    public function decline(string $token): RedirectResponse|View
    {
        $accessToken = PresentationAccessToken::where('token', $token)->first();
        if (! $accessToken || ! $accessToken->isValid()) {
            return view('presentations.public.expired');
        }

        $presentation = $accessToken->presentation;
        $plan = $presentation->treatmentPlan;
        $relationshipId = $presentation->patient?->relationship_id;

        // Deliberately does NOT touch treatment_plans — declining a presentation
        // is a communication-layer fact, not a clinical one.
        $presentation->update(['status' => Presentation::STATUS_DECLINED, 'declined_at' => now()]);

        app(ActivityEngine::class)->log(
            subject:        $presentation,
            event:          'presentation.declined',
            actor:          null,
            metadata:       ['patient_id' => $presentation->patient_id],
            relationshipId: $relationshipId,
            description:    'Patient declined via Smart Presentation',
        );

        // ── Fix (2026-07-12): decline() used to stop here, leaving the sales
        // pipeline unaware — an Opportunity already in 'prospect'/'quoted'
        // stayed stuck forever, and if none existed yet nothing tracked the
        // decline at all. Mirrors accept()'s guarded Opportunity handling
        // above, but finds-or-creates into the 'declined' stage instead. ──
        if ($plan) {
            $opportunity = TreatmentOpportunity::where('treatment_plan_id', $plan->id)->first();

            if ($opportunity) {
                $opportunity->update([
                    'status'          => 'declined',
                    'declined_reason' => $opportunity->declined_reason ?? 'Declined via Smart Presentation',
                ]);
            } else {
                $plan->loadMissing('items');
                $firstItem = $plan->items->first();

                $opportunity = TreatmentOpportunity::create([
                    'patient_id'        => $plan->patient_id,
                    'treatment_plan_id' => $plan->id,
                    'relationship_id'   => $relationshipId,
                    'type'              => 'other',
                    'label'             => $firstItem?->treatment_name ?? $plan->plan_name,
                    'status'            => 'declined',
                    'priority'          => 'medium',
                    'declined_reason'   => 'Declined via Smart Presentation',
                    'created_by'        => $presentation->created_by,
                ]);
            }

            app(ActivityEngine::class)->log(
                subject:        $opportunity,
                event:          'opportunity.declined',
                actor:          null,
                metadata:       ['stage' => 'declined', 'patient_id' => $plan->patient_id, 'source' => 'smart_presentation_declined'],
                relationshipId: $relationshipId,
                description:    'Opportunity marked declined from Smart Presentation',
            );
        }

        return redirect()->route('presentations.public.show', $token);
    }

    /**
     * Phase 3 — "Request a Callback". A third outcome alongside accept/decline:
     * the patient isn't ready to commit either way but wants a human to call
     * them. Lands the Opportunity in the existing 'discussed' ("Nurturing")
     * stage using the follow_up_date field that already existed but was never
     * set anywhere, and moves the Presentation into STATUS_FOLLOW_UP_REQUIRED
     * — a status the model already defined/reserved but never used. No new
     * enum values needed on either model.
     */
    public function requestCallback(string $token): RedirectResponse|View
    {
        $accessToken = PresentationAccessToken::where('token', $token)->first();
        if (! $accessToken || ! $accessToken->isValid()) {
            return view('presentations.public.expired');
        }

        $presentation = $accessToken->presentation;
        $plan = $presentation->treatmentPlan;
        $relationshipId = $presentation->patient?->relationship_id;

        // Guard: don't clobber an accept/decline that already happened (e.g. a
        // stray duplicate POST) with a "follow-up required" status.
        if (! in_array($presentation->status, [Presentation::STATUS_ACCEPTED, Presentation::STATUS_DECLINED], true)) {
            $presentation->update(['status' => Presentation::STATUS_FOLLOW_UP_REQUIRED]);
        }

        app(ActivityEngine::class)->log(
            subject:        $presentation,
            event:          'presentation.follow_up_requested',
            actor:          null,
            metadata:       ['patient_id' => $presentation->patient_id],
            relationshipId: $relationshipId,
            description:    'Patient requested a callback via Smart Presentation',
        );

        // ── Mirrors accept()/decline()'s guarded Opportunity handling above.
        // Won't downgrade an opportunity that's already committed/converted —
        // a callback request after acceptance shouldn't reopen the pipeline. ──
        if ($plan) {
            $opportunity = TreatmentOpportunity::where('treatment_plan_id', $plan->id)->first();

            if ($opportunity) {
                if (! in_array($opportunity->status, ['accepted', 'completed'], true)) {
                    $opportunity->update([
                        'status'         => 'discussed',
                        'follow_up_date' => $opportunity->follow_up_date ?? now()->toDateString(),
                    ]);
                }
            } else {
                $plan->loadMissing('items');
                $firstItem = $plan->items->first();

                $opportunity = TreatmentOpportunity::create([
                    'patient_id'        => $plan->patient_id,
                    'treatment_plan_id' => $plan->id,
                    'relationship_id'   => $relationshipId,
                    'type'              => 'other',
                    'label'             => $firstItem?->treatment_name ?? $plan->plan_name,
                    'status'            => 'discussed',
                    'priority'          => 'high',
                    'follow_up_date'    => now()->toDateString(),
                    'created_by'        => $presentation->created_by,
                ]);
            }

            app(ActivityEngine::class)->log(
                subject:        $opportunity,
                event:          'opportunity.callback_requested',
                actor:          null,
                metadata:       ['stage' => 'discussed', 'patient_id' => $plan->patient_id, 'source' => 'smart_presentation_callback'],
                relationshipId: $relationshipId,
                description:    'Patient requested a callback via Smart Presentation',
            );
        }

        return redirect()->route('presentations.public.show', $token);
    }
}
