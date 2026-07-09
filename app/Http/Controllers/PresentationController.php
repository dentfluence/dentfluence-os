<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Presentation;
use App\Models\PresentationAccessToken;
use App\Models\PresentationMediaItem;
use App\Models\PresentationSnapshot;
use App\Models\Role;
use App\Models\TreatmentMedia;
use App\Models\TreatmentPlan;
use App\Services\Presentations\PresentationLinkService;
use App\Services\Presentations\PresentationSummaryService;
use App\Services\Relationship\ActivityEngine;
use App\Services\Whatsapp\OutboundMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * PresentationController — "Smart Treatment Presentation" module (Slice A+B).
 *
 * Read-only against Patient/Consultation/TreatmentPlan/Billing/Clinical
 * Library; never writes to any of them. Publishes its own activity events
 * via the existing ActivityEngine (same producer pattern TreatmentPlanController
 * already uses for 'treatment_plan.accepted') rather than inventing a new
 * event system. See docs/plan-smart-treatment-presentation.md.
 *
 * Slice C (send/share, secure links, WhatsApp) and Slice D (Presentations
 * list activity/follow-up) are NOT built here — this controller stops at
 * Finalize, which is the honest boundary of what "build slice A+B" covers.
 */
class PresentationController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $presentations = Presentation::with(['patient', 'treatmentPlan', 'creator'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('presentations.index', compact('presentations', 'status'));
    }

    /**
     * The single new entry point on the Treatment Plan tab: "Create Smart
     * Presentation". Reuses an existing draft for this plan if one already
     * exists, instead of creating a duplicate on repeat clicks.
     */
    public function createFromPlan(TreatmentPlan $plan): RedirectResponse
    {
        $this->ensureAuthor();

        $presentation = Presentation::firstOrCreate(
            [
                'treatment_plan_id' => $plan->id,
                'status'            => Presentation::STATUS_DRAFT,
            ],
            [
                'patient_id'       => $plan->patient_id,
                'consultation_id'  => $plan->consultation_id,
                'created_by'       => Auth::id(),
            ]
        );

        if ($presentation->wasRecentlyCreated) {
            app(ActivityEngine::class)->log(
                subject:        $presentation,
                event:          'presentation.created',
                actor:          Auth::user(),
                metadata:       ['patient_id' => $presentation->patient_id, 'treatment_plan_id' => $plan->id],
                relationshipId: $plan->patient?->relationship_id,
                description:    'Smart presentation created from treatment plan',
            );
        }

        return redirect()->route('presentations.builder', $presentation);
    }

    public function builder(Presentation $presentation): View
    {
        $presentation->load(['patient', 'consultation', 'treatmentPlan.items', 'mediaItems.treatmentMedia']);

        $plan = $presentation->treatmentPlan;
        $treatmentIds = $plan?->items->pluck('treatment_id')->unique()->filter()->values() ?? collect();

        // Candidate media: anything in the Clinical Library tied to a procedure
        // on this plan (TreatmentMedia.treatment_id — the only reliably-linked
        // media system today, see docs/plan-smart-treatment-presentation.md §6).
        $availableMedia = TreatmentMedia::whereIn('treatment_id', $treatmentIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $selectedMediaIds = $presentation->mediaItems->where('included', true)->pluck('treatment_media_id');

        // Slice D: activity timeline — reuses the existing Activity log
        // (ActivityEngine), no new table. Scoped to this presentation only.
        $activity = \App\Models\Activity::where('subject_type', Presentation::class)
            ->where('subject_id', $presentation->id)
            ->orderByDesc('occurred_at')
            ->get();

        $activeToken = $presentation->activeAccessToken();
        $activeTokenUrl = $activeToken ? app(PresentationLinkService::class)->url($activeToken) : null;

        return view('presentations.builder', [
            'presentation'     => $presentation,
            'costSummary'      => $presentation->currentCostSummary(),
            'availableMedia'   => $availableMedia,
            'selectedMediaIds' => $selectedMediaIds,
            'canAuthor'        => $this->isAuthor(),
            'canOperate'       => Auth::user()->canAccess('presentations', 'edit'),
            'activity'         => $activity,
            'activeToken'      => $activeToken,
            'activeTokenUrl'   => $activeTokenUrl,
        ]);
    }

    /**
     * Save the dentist's edits — AI summary text, personal message, and which
     * Clinical Library media items are included. Author-only (Doctor/Admin).
     */
    public function update(Request $request, Presentation $presentation): RedirectResponse
    {
        $this->ensureAuthor();
        $this->ensureEditable($presentation);

        $data = $request->validate([
            'ai_summary_text' => ['nullable', 'string', 'max:8000'],
            'doctor_message'  => ['nullable', 'string', 'max:2000'],
            'media_ids'       => ['array'],
            'media_ids.*'     => ['integer', 'exists:treatment_media,id'],
        ]);

        $presentation->update([
            'ai_summary_text' => $data['ai_summary_text'] ?? null,
            'doctor_message'  => $data['doctor_message'] ?? null,
        ]);

        $this->syncMedia($presentation, $data['media_ids'] ?? []);

        return redirect()->route('presentations.builder', $presentation)
            ->with('success', 'Draft saved.');
    }

    /**
     * Ask the local AI to draft/redraft the patient-facing summary. The
     * result always lands back in the editable textarea — nothing is ever
     * sent to a patient without a dentist reviewing it first (see finalize()).
     */
    public function generateSummary(Presentation $presentation): RedirectResponse
    {
        $this->ensureAuthor();
        $this->ensureEditable($presentation);

        try {
            $summary = app(PresentationSummaryService::class)->generate($presentation);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('presentations.builder', $presentation)
                ->with('error', 'The AI assistant is unavailable right now — please write the summary manually, or try again shortly.');
        }

        $presentation->update(['ai_summary_text' => $summary]);

        return redirect()->route('presentations.builder', $presentation)
            ->with('success', 'AI draft generated — please review and edit before finalizing.');
    }

    /**
     * Hard review gate: reviewed_at is only ever set here, and only once the
     * dentist has explicitly confirmed the checkbox — never automatically.
     * Also takes the immutable point-in-time snapshot (see PresentationSnapshot).
     */
    public function finalize(Request $request, Presentation $presentation): RedirectResponse
    {
        $this->ensureAuthor();
        $this->ensureEditable($presentation);

        $request->validate([
            'confirm_reviewed' => ['accepted'],
        ], [
            'confirm_reviewed.accepted' => 'You must confirm you have reviewed the summary for clinical accuracy before finalizing.',
        ]);

        if (blank($presentation->ai_summary_text)) {
            return redirect()->route('presentations.builder', $presentation)
                ->with('error', 'Add a summary (write your own or generate one) before finalizing.');
        }

        $presentation->update([
            'status'      => Presentation::STATUS_FINALIZED,
            'reviewed_at' => now(),
        ]);

        PresentationSnapshot::updateOrCreate(
            ['presentation_id' => $presentation->id],
            ['snapshot' => $this->buildSnapshot($presentation)]
        );

        app(ActivityEngine::class)->log(
            subject:        $presentation,
            event:          'presentation.finalized',
            actor:          Auth::user(),
            metadata:       ['patient_id' => $presentation->patient_id],
            relationshipId: $presentation->patient?->relationship_id,
            description:    'Smart presentation finalized, ready to send',
        );

        return redirect()->route('presentations.builder', $presentation)
            ->with('success', 'Finalized. Sending (WhatsApp/secure link) is coming in the next build slice.');
    }

    /** Only an un-sent draft may be deleted — never a finalized/sent presentation. */
    public function destroy(Presentation $presentation): RedirectResponse
    {
        $this->ensureAuthor();

        abort_unless($presentation->status === Presentation::STATUS_DRAFT, 422, 'Only a draft presentation can be deleted.');

        $presentation->delete();

        return redirect()->route('presentations.index')->with('success', 'Draft deleted.');
    }

    // ── Slice C: Send / Resend ───────────────────────────────────────────────
    // Operate actions — Doctor AND Front Desk/Manager (module:presentations,edit
    // is enough here; NOT author-gated, since sending/resending is exactly the
    // "staff can operate" half of the permission split, not the "devise" half.

    public function send(Presentation $presentation): RedirectResponse
    {
        abort_if($presentation->status !== Presentation::STATUS_FINALIZED, 422, 'Only a finalized presentation can be sent.');

        $token = app(PresentationLinkService::class)->issue($presentation);
        $result = $this->deliverWhatsApp($presentation, $token);

        if (! $result['ok']) {
            return redirect()->route('presentations.builder', $presentation)
                ->with('error', 'Could not send via WhatsApp: ' . ($result['reason'] ?? 'unknown error') . '. The link is ready — you can share it manually.');
        }

        $presentation->update(['status' => Presentation::STATUS_SENT, 'sent_at' => now()]);

        app(ActivityEngine::class)->log(
            subject:        $presentation,
            event:          'presentation.sent',
            actor:          Auth::user(),
            metadata:       ['patient_id' => $presentation->patient_id, 'channel' => 'whatsapp'],
            relationshipId: $presentation->patient?->relationship_id,
            description:    'Smart presentation sent to patient',
        );

        return redirect()->route('presentations.builder', $presentation)->with('success', 'Sent via WhatsApp.');
    }

    /**
     * Always issues a fresh token and auto-revokes the old one (never leave two
     * live links). If the gap since the last send is large enough that the plan
     * may have changed, checks staleness first and asks for a re-review instead
     * of blindly resending — per the confirmed resend behavior.
     */
    public function resend(Presentation $presentation): RedirectResponse
    {
        abort_if(! in_array($presentation->status, [
            Presentation::STATUS_SENT, Presentation::STATUS_VIEWED,
            Presentation::STATUS_DECLINED, Presentation::STATUS_FOLLOW_UP_REQUIRED,
        ], true), 422, 'This presentation has not been sent yet — use Send instead.');

        $linkService = app(PresentationLinkService::class);
        $daysSince = $linkService->daysSinceLastSend($presentation);

        if ($daysSince !== null && $daysSince >= 30 && $linkService->isStale($presentation)) {
            return redirect()->route('presentations.builder', $presentation)
                ->with('error', "It's been {$daysSince} days and the plan or cost has changed since this was last sent — please review before resending.");
        }

        $token = $linkService->issue($presentation);
        $result = $this->deliverWhatsApp($presentation, $token);

        if (! $result['ok']) {
            return redirect()->route('presentations.builder', $presentation)
                ->with('error', 'Could not resend via WhatsApp: ' . ($result['reason'] ?? 'unknown error') . '. The link is ready — you can share it manually.');
        }

        $presentation->update(['status' => Presentation::STATUS_SENT, 'sent_at' => now()]);

        app(ActivityEngine::class)->log(
            subject:        $presentation,
            event:          'presentation.sent',
            actor:          Auth::user(),
            metadata:       ['patient_id' => $presentation->patient_id, 'channel' => 'whatsapp', 'resend' => true],
            relationshipId: $presentation->patient?->relationship_id,
            description:    'Smart presentation resent to patient',
        );

        return redirect()->route('presentations.builder', $presentation)->with('success', 'Resent — a fresh link was issued and the old one revoked.');
    }

    protected function deliverWhatsApp(Presentation $presentation, PresentationAccessToken $token): array
    {
        $patient = $presentation->patient;
        if (! $patient?->phone) {
            return ['ok' => false, 'reason' => 'This patient has no phone number on file.'];
        }

        $clinicName = AppSetting::get('clinic_name', config('app.name', 'our clinic'));
        $url = app(PresentationLinkService::class)->url($token);
        $body = "Hi {$patient->name}, here's your treatment plan from {$clinicName}: {$url}";

        return app(OutboundMessageService::class)->sendText($patient->phone, $body, [
            'patient_id' => $patient->id,
            'category'   => 'service',
        ]);
    }

    // ── Slice D: Follow-up / Decline (staff-operate, not author-only) ───────

    public function markDeclined(Presentation $presentation): RedirectResponse
    {
        $presentation->update(['status' => Presentation::STATUS_DECLINED, 'declined_at' => now()]);

        app(ActivityEngine::class)->log(
            subject:        $presentation,
            event:          'presentation.declined',
            actor:          Auth::user(),
            metadata:       ['patient_id' => $presentation->patient_id],
            relationshipId: $presentation->patient?->relationship_id,
            description:    'Smart presentation marked declined by staff',
        );

        return redirect()->route('presentations.builder', $presentation)->with('success', 'Marked declined.');
    }

    public function markFollowUp(Request $request, Presentation $presentation): RedirectResponse
    {
        $data = $request->validate(['follow_up_notes' => ['nullable', 'string', 'max:2000']]);

        $presentation->update([
            'status'          => Presentation::STATUS_FOLLOW_UP_REQUIRED,
            'follow_up_notes' => $data['follow_up_notes'] ?? $presentation->follow_up_notes,
        ]);

        app(ActivityEngine::class)->log(
            subject:        $presentation,
            event:          'presentation.follow_up_required',
            actor:          Auth::user(),
            metadata:       ['patient_id' => $presentation->patient_id],
            relationshipId: $presentation->patient?->relationship_id,
            description:    'Follow-up logged for smart presentation',
        );

        return redirect()->route('presentations.builder', $presentation)->with('success', 'Follow-up note saved.');
    }

    // ── Slice E: Shared Links ────────────────────────────────────────────────

    public function linksIndex(): View
    {
        $tokens = PresentationAccessToken::with('presentation.patient')->latest()->paginate(30);

        return view('presentations.links', compact('tokens'));
    }

    /** Route param is named {link} (bound by row id) — distinct from the secret token string column. */
    public function linkRevoke(PresentationAccessToken $link): RedirectResponse
    {
        app(PresentationLinkService::class)->revoke($link);

        return back()->with('success', 'Link revoked.');
    }

    public function linkRegenerate(Presentation $presentation): RedirectResponse
    {
        app(PresentationLinkService::class)->issue($presentation);

        return back()->with('success', 'A fresh link was issued and the previous one revoked.');
    }

    // ── Slice E: Settings ────────────────────────────────────────────────────

    public function settingsShow(): View
    {
        $defaultExpiryDays = (int) AppSetting::get(
            PresentationLinkService::DEFAULT_EXPIRY_DAYS_SETTING,
            PresentationLinkService::DEFAULT_EXPIRY_DAYS
        );

        return view('presentations.settings', compact('defaultExpiryDays'));
    }

    public function settingsUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'default_expiry_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        AppSetting::set(PresentationLinkService::DEFAULT_EXPIRY_DAYS_SETTING, (int) $data['default_expiry_days'], 'presentations');

        return redirect()->route('presentations.settings')->with('success', 'Settings saved.');
    }

    // ── Internal helpers ─────────────────────────────────────────────────────

    protected function syncMedia(Presentation $presentation, array $includedIds): void
    {
        $treatmentIds = $presentation->treatmentPlan?->items->pluck('treatment_id')->unique()->filter() ?? collect();
        $candidateMediaIds = TreatmentMedia::whereIn('treatment_id', $treatmentIds)->pluck('id');

        foreach ($candidateMediaIds as $mediaId) {
            PresentationMediaItem::updateOrCreate(
                ['presentation_id' => $presentation->id, 'treatment_media_id' => $mediaId],
                ['included' => in_array($mediaId, $includedIds, true)]
            );
        }
    }

    protected function buildSnapshot(Presentation $presentation): array
    {
        $plan = $presentation->treatmentPlan;

        return [
            'taken_at'        => now()->toIso8601String(),
            'patient_name'    => $presentation->patient?->name,
            'patient_age'     => $presentation->patient?->age,
            'patient_gender'  => $presentation->patient?->gender,
            'primary_diagnosis' => $presentation->consultation?->primary_diagnosis,
            'plan_name'       => $plan?->plan_name,
            'items'           => $plan?->items->map(fn ($i) => [
                'treatment_name' => $i->treatment_name,
                'tooth_number'   => $i->tooth_number,
                'units'          => $i->units,
                'total'          => (float) $i->total,
            ])->values()->all() ?? [],
            'cost'            => $presentation->currentCostSummary(),
            'ai_summary_text' => $presentation->ai_summary_text,
            'doctor_message'  => $presentation->doctor_message,
            'included_media_ids' => $presentation->mediaItems()->where('included', true)->pluck('treatment_media_id')->all(),
        ];
    }

    /** Doctor/Admin only — the dentist devises and owns all clinical content. */
    protected function isAuthor(): bool
    {
        $user = Auth::user();
        $isLegacyAdmin = $user->role === 'admin' && ! $user->role_id;
        $slug = $user->roleModel?->slug;

        return $isLegacyAdmin || in_array($slug, [Role::DOCTOR, Role::ADMIN], true);
    }

    protected function ensureAuthor(): void
    {
        abort_unless($this->isAuthor(), 403, 'Only a dentist can create or edit a Smart Presentation.');
    }

    protected function ensureEditable(Presentation $presentation): void
    {
        abort_if($presentation->status !== Presentation::STATUS_DRAFT, 422, 'This presentation is already finalized and can no longer be edited here.');
    }
}
