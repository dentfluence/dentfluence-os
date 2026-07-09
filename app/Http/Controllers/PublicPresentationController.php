<?php

namespace App\Http\Controllers;

use App\Models\Presentation;
use App\Models\PresentationAccessToken;
use App\Models\TreatmentOpportunity;
use App\Models\User;
use App\Services\Relationship\ActivityEngine;
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

        return view('presentations.public.show', [
            'presentation' => $presentation,
            'costSummary'  => $presentation->currentCostSummary(),
            'includedMedia' => $presentation->mediaItems->where('included', true)->pluck('treatmentMedia')->filter(),
            'token'        => $token,
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

        // ── Mirrors TreatmentPlanController::accept() exactly — same fields,
        // same event name, same guarded Opportunity creation — so a patient
        // accepting via the presentation is indistinguishable downstream from
        // an in-clinic acceptance. See class docblock. ──
        $plan->update(['accepted_at' => now(), 'status' => 'ongoing']);
        $plan->load(['items', 'patient']);

        app(ActivityEngine::class)->log(
            subject:        $plan,
            event:          'treatment_plan.accepted',
            actor:          $actor,
            metadata:       ['patient_id' => $plan->patient_id, 'via' => 'smart_presentation'],
            relationshipId: $relationshipId,
            description:    'Treatment plan accepted by patient via Smart Presentation',
        );

        if (! TreatmentOpportunity::where('treatment_plan_id', $plan->id)->exists()) {
            $firstItem = $plan->items->first();

            $opportunity = TreatmentOpportunity::create([
                'patient_id'        => $plan->patient_id,
                'treatment_plan_id' => $plan->id,
                'relationship_id'   => $relationshipId,
                'type'              => 'other',
                'label'             => $firstItem?->treatment_name ?? $plan->plan_name,
                'status'            => 'prospect',
                'priority'          => 'medium',
                'created_by'        => $presentation->created_by,
            ]);

            app(ActivityEngine::class)->log(
                subject:        $opportunity,
                event:          'opportunity.created',
                actor:          $actor,
                metadata:       ['stage' => 'prospect', 'patient_id' => $plan->patient_id, 'source' => 'treatment_plan_accepted'],
                relationshipId: $relationshipId,
                description:    'Opportunity created from accepted treatment plan',
            );
        }

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

        // Deliberately does NOT touch treatment_plans — declining a presentation
        // is a communication-layer fact, not a clinical one.
        $presentation->update(['status' => Presentation::STATUS_DECLINED, 'declined_at' => now()]);

        app(ActivityEngine::class)->log(
            subject:        $presentation,
            event:          'presentation.declined',
            actor:          null,
            metadata:       ['patient_id' => $presentation->patient_id],
            relationshipId: $presentation->patient?->relationship_id,
            description:    'Patient declined via Smart Presentation',
        );

        return redirect()->route('presentations.public.show', $token);
    }
}
