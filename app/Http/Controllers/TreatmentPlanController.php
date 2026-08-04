<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Patient;
use App\Models\PatientJourney;
use App\Models\Presentation;
use App\Models\TreatmentOpportunity;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\Treatment;
use App\Services\Relationship\ActivityEngine;
use App\Services\TreatmentPlan\ConsentDocumentService;
use App\Services\TreatmentPlan\TreatmentPlanAcceptanceService;
use App\Services\TreatmentPlan\TreatmentPlanOpportunitySync;
use App\Support\QrCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TreatmentPlanController extends Controller
{
    // ── Print: multi-plan comparison document ───────────────────────────────
    //
    // Route:  GET /treatment-plans/print?ids[]=1&ids[]=2
    // Accepts 1-3 plan IDs; renders a clean A4 clinic print document.
    //

    public function printView(Request $request)
    {
        $ids = array_filter((array) $request->query('ids', []));

        abort_if(empty($ids), 400, 'No plan IDs provided.');
        abort_if(count($ids) > 30, 400, 'Too many plans.');

        // Load plans in the order the IDs were passed
        $plans = TreatmentPlan::with(['items', 'patient', 'consultation.doctor', 'doctor', 'creator'])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn($p) => array_search($p->id, $ids))
            ->values();

        abort_if($plans->isEmpty(), 404, 'Plans not found.');

        // F4 — this endpoint takes an arbitrary list of plan ids, so it is the
        // easiest place to read plans that are not the caller's to read. Every
        // plan must be accessible, and they must all belong to ONE patient:
        // a comparison print of two patients' plans is never a real document.
        foreach ($plans as $plan) {
            $this->assertPlanAccessible($plan);
        }

        abort_if($plans->pluck('patient_id')->unique()->count() > 1, 400,
            'Treatment plans from different patients cannot be printed together.');

        // Assign A/B/C internal letters
        $letters = ['A', 'B', 'C', 'D', 'E'];
        foreach ($plans as $idx => $plan) {
            $plan->plan_letter = $letters[$idx] ?? ($idx + 1);
        }

        // ── Optional Case Journey QR ──────────────────────────────────────
        // OPT-IN only: the "scan to explore your plan" QR is added to the
        // printout ONLY when the print is requested with ?qr=1, AND a Case
        // Journey has already been sent for that plan. Never creates a journey
        // or token here — a plan with no sent journey just prints without a QR.
        // (Smart Presentation retired 2026-07-15; the journey link replaces it.)
        if ($request->boolean('qr')) {
            foreach ($plans as $plan) {
                if ($url = PatientJourney::activeLinkUrlForPlan($plan->id)) {
                    $plan->presentation_url = $url;
                    $plan->presentation_qr  = QrCodeGenerator::dataUri($url);
                }
            }
        }

        // Use patient + consultation from the first plan
        $firstPlan   = $plans->first();
        $patient     = $firstPlan->patient;
        $consultation = $firstPlan->consultation;

        // Clinic info from settings
        $clinicName = AppSetting::get('clinic_name', config('app.clinic_name', 'Dental Clinic'));
        $clinicLogo = AppSetting::get('clinic_logo');

        return view('treatment-plans.print', compact(
            'plans',
            'patient',
            'consultation',
            'clinicName',
            'clinicLogo'
        ));
    }

    // ── Phase 2: Clinical consent document ───────────────────────────────────
    //
    // GET /treatment-plans/{plan}/consent
    // Merges each item's treatment consent text (SOP consent_notes, falling
    // back to the Intelligence-tab consent_template) with this plan's actual
    // teeth and procedures, and persists an immutable snapshot row so there
    // is always a record of exactly what the patient was shown. Separate
    // from the DPDP consent module. No e-signature — wet-ink print only.
    //

    public function consentPrint(Request $request, TreatmentPlan $plan, ConsentDocumentService $consents)
    {
        $this->assertPlanAccessible($plan);

        $plan->load(['patient', 'consultation.doctor', 'doctor']);

        // Selection from the "Consent Form" picker on the plan tab — an array
        // of "{item_id}|{tooth}" keys (tooth blank for whole-mouth items). If
        // absent (e.g. an old bookmarked link), fall back to the original
        // behaviour: every item flagged consent_required.
        $selected     = $request->query('sel');
        $selectedKeys = is_array($selected) ? array_values($selected) : null;

        $consent = $consents->generateAndPersist($plan, Auth::id(), $selectedKeys);

        $clinicName = AppSetting::get('clinic_name', config('app.clinic_name', 'Dental Clinic'));
        $clinicLogo = AppSetting::get('clinic_logo');

        return view('treatment-consents.print', [
            'plan'         => $plan,
            'patient'      => $plan->patient,
            'consultation' => $plan->consultation,
            'sections'     => $consent->sections,
            'generatedAt'  => $consent->created_at,
            'clinicName'   => $clinicName,
            'clinicLogo'   => $clinicLogo,
        ]);
    }

    // ── Return items for a single plan (used by visit form AJAX) ────────────

    public function getItems(TreatmentPlan $plan): JsonResponse
    {
        $this->assertPlanAccessible($plan);

        return response()->json([
            'success' => true,
            'items'   => $plan->items->map(fn($i) => [
                'id'             => $i->id,
                'treatment_name' => $i->treatment_name,
                'tooth_number'   => $i->tooth_number,
                'unit_price'     => (float)$i->unit_price,
                'option_rank'    => $i->option_rank,
                'notes'          => $i->notes,
                'status'         => $i->status,
            ])->values(),
        ]);
    }

    // ── List all plans for a patient ─────────────────────────────────────────

    public function index(Patient $patient): JsonResponse
    {
        $plans = $patient->treatmentPlans()
            ->with(['items', 'creator', 'opportunity'])
            ->latest()
            ->get()
            ->map(fn($p) => $this->formatPlan($p));

        return response()->json(['success' => true, 'plans' => $plans]);
    }

    // ── Create a new treatment option ────────────────────────────────────────

    public function store(Request $request, Patient $patient): JsonResponse
    {
        $request->validate([
            'plan_name'          => ['nullable', 'string', 'max:100'],
            'consultation_id'    => ['nullable', 'exists:consultations,id'],
            'doctor_id'          => ['nullable', 'exists:users,id'],
            'plan_date'          => ['nullable', 'date'],
            'estimated_duration' => ['nullable', 'string', 'max:50'],
            'visit_count'        => ['nullable', 'integer', 'min:1'],
            'doctor_notes'       => ['nullable', 'string'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.tooth_number'      => ['nullable', 'string', 'max:100'],
            'items.*.treatment_name'    => ['required', 'string', 'max:150'],
            'items.*.unit_price'        => ['required', 'numeric', 'min:0'],
            'items.*.units'             => ['nullable', 'integer', 'min:1'],
            'items.*.notes'             => ['nullable', 'string', 'max:2000'],
            'items.*.treatment_id'      => ['nullable', 'exists:treatments,id'],
            'items.*.consent_required'  => ['nullable', 'boolean'],
            // F3 — pricing modifiers the plan writes are now declared, so they
            // are bounded rather than accepted raw from the request.
            'items.*.disc_pct'          => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.gst_pct'           => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.option_rank'       => ['nullable', 'in:best,acceptable,alternative'],
            'items.*.aocp_applied'      => ['nullable', 'boolean'],
            'items.*.status'            => ['prohibited'],
        ], [
            'items.*.status.prohibited' => $this->lifecycleLockdownMessage(),
        ]);

        $plan = DB::transaction(function () use ($request, $patient) {
            // Auto-name: "Treatment Option 1", "Option 2" etc. per consultation
            $existingCount = $patient->treatmentPlans()
                ->when($request->consultation_id, fn($q) => $q->where('consultation_id', $request->consultation_id))
                ->count();

            $plan = TreatmentPlan::create([
                'patient_id'         => $patient->id,
                'consultation_id'    => $request->consultation_id,
                'doctor_id'          => $request->doctor_id,
                'plan_date'          => $request->plan_date ?: now()->toDateString(),
                'plan_name'          => $request->plan_name ?? ('Treatment Option ' . ($existingCount + 1)),
                'display_order'      => $existingCount + 1,
                'status'             => 'pending',
                'created_by'         => Auth::id(),
                'estimated_duration' => $request->estimated_duration,
                'visit_count'        => $request->visit_count,
                'doctor_notes'       => $request->doctor_notes,
            ]);

            $this->syncItems($plan, $request->items, 0);
            $plan->update(['total' => $plan->items()->sum('total')]);

            return $plan;
        });

        $plan->load(['items', 'creator']);

        // Additive Activity log (docs/backend-orchestration-plan.md §2.4) — no
        // rule currently matches 'treatment_plan.created', feeds Insights only.
        app(ActivityEngine::class)->log(
            subject:        $plan,
            event:          'treatment_plan.created',
            actor:          Auth::user(),
            metadata:       ['patient_id' => $patient->id],
            relationshipId: $patient->relationship_id,
            description:    'Treatment plan created',
        );

        return response()->json([
            'success' => true,
            'message' => 'Treatment option created.',
            'plan'    => $this->formatPlan($plan),
        ]);
    }

    // ── Update a treatment option ─────────────────────────────────────────────

    public function update(Request $request, TreatmentPlan $plan): JsonResponse
    {
        $this->assertPlanAccessible($plan);

        $request->validate([
            'plan_name'          => ['nullable', 'string', 'max:100'],
            'doctor_id'          => ['nullable', 'exists:users,id'],
            'plan_date'          => ['nullable', 'date'],
            'estimated_duration' => ['nullable', 'string', 'max:50'],
            'visit_count'        => ['nullable', 'integer', 'min:1'],
            'doctor_notes'       => ['nullable', 'string'],
            // Slice 2.3e — LIFECYCLE LOCKDOWN. A plan's status may no longer be
            // set through the generic edit form. Lifecycle truth comes only from
            // the canonical verbs (Present / Accept / Partial / Defer / Reject).
            // Rejected by the rule below with a message that teaches the verb.
            'status'             => ['prohibited'],
            'items'              => ['nullable', 'array'],
            'items.*.id'                => ['nullable', 'exists:treatment_plan_items,id'],
            'items.*.tooth_number'      => ['nullable', 'string', 'max:100'],
            'items.*.treatment_name'    => ['required_with:items', 'string', 'max:150'],
            'items.*.unit_price'        => ['required_with:items', 'numeric', 'min:0'],
            'items.*.units'             => ['nullable', 'integer', 'min:1'],
            'items.*.notes'             => ['nullable', 'string', 'max:2000'],
            'items.*.treatment_id'      => ['nullable', 'exists:treatments,id'],
            'items.*.consent_required'  => ['nullable', 'boolean'],
            // F3 — see store(). Item lifecycle state is owned by the plan and
            // is refused here for the same reason plan status is.
            'items.*.disc_pct'          => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.gst_pct'           => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.option_rank'       => ['nullable', 'in:best,acceptable,alternative'],
            'items.*.aocp_applied'      => ['nullable', 'boolean'],
            'items.*.status'            => ['prohibited'],
        ], [
            'status.prohibited'         => $this->lifecycleLockdownMessage(),
            'items.*.status.prohibited' => $this->lifecycleLockdownMessage(),
        ]);

        DB::transaction(function () use ($request, $plan) {
            $plan->update(array_filter([
                'plan_name'          => $request->plan_name,
                'estimated_duration' => $request->estimated_duration,
                'visit_count'        => $request->visit_count,
                'doctor_notes'       => $request->doctor_notes,
            ], fn($v) => !is_null($v)));

            // doctor_id set outside the array_filter so it can also be cleared
            // (null = fall back to consultation doctor on prints).
            if ($request->exists('doctor_id')) {
                $plan->update(['doctor_id' => $request->doctor_id ?: null]);
            }

            // plan_date likewise set outside array_filter so it can be cleared
            // (null = fall back to consultation date, then today, on prints).
            if ($request->exists('plan_date')) {
                $plan->update(['plan_date' => $request->plan_date ?: null]);
            }

            if ($request->has('items')) {
                $keptIds = collect($request->items)->pluck('id')->filter()->all();

                // Never delete work that has already been done or billed.
                // A plan revision re-sends only the pending rows, so the old
                // blanket `whereNotIn(...)->delete()` silently hard-deleted
                // completed and already-invoiced line items (and their
                // plan↔invoice linkage) whenever a dentist revised an ongoing
                // plan. Those rows are now protected regardless of the payload.
                $protectedIds = $plan->items()
                    ->where(function ($q) {
                        $q->where('status', 'completed')
                          ->orWhereIn('billing_progress', [
                              TreatmentPlanItem::PROGRESS_PARTIAL,
                              TreatmentPlanItem::PROGRESS_COMPLETED,
                              TreatmentPlanItem::PROGRESS_INVOICED,
                          ])
                          ->orWhere('invoiced_units', '>', 0)
                          ->orWhereHas('teeth', fn ($t) => $t->where('status', '!=', 'pending'));
                    })
                    ->pluck('id')
                    ->all();

                $plan->items()
                    ->whereNotIn('id', array_merge($keptIds, $protectedIds))
                    ->delete();

                $this->syncItems($plan, $request->items, 0);
                $plan->update(['total' => $plan->items()->sum('total')]);
            }
        });

        $plan->load(['items', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Treatment option updated.',
            'plan'    => $this->formatPlan($plan),
        ]);
    }

    // ── Mark as Accepted ─────────────────────────────────────────────────────
    //
    // POST /treatment-plans/{plan}/accept
    // Marks the chosen option as accepted and locks it.
    // Any other options for the same consultation remain as-is (for history).
    //

    public function accept(TreatmentPlan $plan, TreatmentPlanAcceptanceService $acceptance): JsonResponse
    {
        $this->assertPlanAccessible($plan);

        // Acceptance orchestration (stamp + Timeline log + guarded Opportunity)
        // lives in TreatmentPlanAcceptanceService — shared with the Smart
        // Presentation and mobile accept paths so all three produce identical
        // downstream records. See docs/backend-orchestration-plan.md §2.5.
        $plan = $acceptance->accept($plan, Auth::user(), via: 'clinic');

        return response()->json([
            'success' => true,
            'message' => 'Treatment option accepted.',
            'plan'    => $this->formatPlan($plan),
        ]);
    }

    // ── Mark as Presented ─────────────────────────────────────────────────────
    // Records that this plan's estimate was shown to the patient, which lands it
    // in the Opportunity pipeline at "Estimate Given" and keeps it there until
    // the plan is accepted (→ Converted) or declined. Idempotent — safe to click
    // twice; the one-opportunity-per-plan guard lives in the sync service.
    public function markPresented(TreatmentPlan $plan, \App\Services\TreatmentPlan\TreatmentPlanPresentationService $presentation): JsonResponse
    {
        $this->assertPlanAccessible($plan);

        // Slice 2.2: routed through the canonical presentation service, which
        // records the CLINICAL fact (presented_at, first time only) and then
        // projects it onto the Opportunity board exactly as before — so staff
        // workflow is unchanged while clinical truth stops depending on a
        // sales stage. The API uses this same service.
        try {
            $result = $presentation->markPresented($plan, Auth::user(), 'clinic');
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['first_presentation']
                ? 'Plan and estimate presented to the patient.'
                : 'Plan presented again — the original presentation date is unchanged.',
            'plan'    => $this->formatPlan($result['plan']->fresh(['items', 'creator', 'opportunity'])),
        ]);
    }

    /**
     * The message a user sees if they try to set a plan's lifecycle status
     * directly. It names the verb they actually want rather than just refusing,
     * because the commonest misuse was cancelling a plan to mean "patient said
     * no" — which destroyed the difference between an administrative closure
     * and a clinical decision.
     */
    public static function lifecycleLockdownMessage(): string
    {
        return 'A treatment plan\'s status cannot be changed from the edit form. '
             . 'Use Reject Plan when the patient declines, Defer when they want to decide later, '
             . 'or Accept / Partial Acceptance when they agree. '
             . 'Completion follows the treatment itself and is never set by hand.';
    }

    // ── Patient decisions (Slice 2.3e) ────────────────────────────────────────
    //
    // Reject / Defer / Partial acceptance. Each is a thin door onto the one
    // canonical decision service — no business logic lives here, so web, API
    // and any future patient channel produce identical clinical truth.

    /** The patient explicitly declined this plan. */
    public function reject(Request $request, TreatmentPlan $plan, TreatmentPlanAcceptanceService $decisions): JsonResponse
    {
        $this->assertPlanAccessible($plan);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $plan = $decisions->reject($plan, $data['reason'] ?? null, Auth::user(), 'clinic');
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Recorded: the patient declined this plan.',
            'plan'    => $this->formatPlan($plan->fresh(['items', 'creator', 'opportunity'])),
        ]);
    }

    /** "Not now" — still a live plan; a review date is optional. */
    public function defer(Request $request, TreatmentPlan $plan, TreatmentPlanAcceptanceService $decisions): JsonResponse
    {
        $this->assertPlanAccessible($plan);

        $data = $request->validate([
            'defer_until' => ['nullable', 'date', 'after_or_equal:today'],
            'reason'      => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $plan = $decisions->defer(
                $plan,
                $data['defer_until'] ?? null,
                $data['reason'] ?? null,
                Auth::user(),
                'clinic',
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => ! empty($data['defer_until'])
                ? 'Recorded: the patient will decide later. Nobody will chase until ' . $data['defer_until'] . '.'
                : 'Recorded: the patient will decide later.',
            'plan'    => $this->formatPlan($plan->fresh(['items', 'creator', 'opportunity'])),
        ]);
    }

    /** The patient agreed to some treatments and not others. */
    public function partialAccept(Request $request, TreatmentPlan $plan, TreatmentPlanAcceptanceService $decisions): JsonResponse
    {
        $this->assertPlanAccessible($plan);

        $data = $request->validate([
            'items'   => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'string', 'in:' . implode(',', array_keys(\App\Models\PlanDecisionItem::DECISIONS))],
            'notes'   => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $plan = $decisions->acceptPartially($plan, $data['items'], Auth::user(), 'clinic', $data['notes'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Recorded: the patient accepted part of this plan.',
            'plan'    => $this->formatPlan($plan->fresh(['items', 'creator', 'opportunity'])),
        ]);
    }

    // ── Revert acceptance ─────────────────────────────────────────────────────
    //
    // Un-accepts a previously accepted plan. A reason is REQUIRED and is written
    // to the staff activity log for audit. Reverting is blocked if any invoice is
    // already linked to the plan (you can't un-accept something already billed).
    //
    public function revert(Request $request, TreatmentPlan $plan): JsonResponse
    {
        $this->assertPlanAccessible($plan);

        // Reason is mandatory — it's recorded in the log.
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        // Shared brain — same billing guard + audit as the mobile API
        // (TreatmentPlanAcceptanceService::revert, consolidated 2026-07-14).
        try {
            $plan = app(\App\Services\TreatmentPlan\TreatmentPlanAcceptanceService::class)
                ->revert($plan, $request->input('reason'), $request->user(), 'clinic');
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Treatment option acceptance reverted.',
            'plan'    => $this->formatPlan($plan),
        ]);
    }

    // ── Delete a plan ────────────────────────────────────────────────────────

    public function destroy(TreatmentPlan $plan): JsonResponse
    {
        $this->assertPlanAccessible($plan);

        // Billing guard — mirrors revert(). Deleting a plan that already has
        // invoices orphans the billing linkage, so it's refused outright.
        if ($plan->invoices()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete: this plan already has invoices/billing against it. Cancel the plan instead.',
            ], 422);
        }

        $plan->delete();

        return response()->json(['success' => true, 'message' => 'Treatment plan deleted.']);
    }

    // ── Delete a single item ─────────────────────────────────────────────────

    public function destroyItem(TreatmentPlanItem $item): JsonResponse
    {
        // F4 — the item is bound straight from the URL, so ownership is
        // established through its plan before anything else happens.
        $item->loadMissing('plan');
        abort_if(! $item->plan, 404, 'Treatment plan item not found.');
        $this->assertPlanAccessible($item->plan);

        // Same protection as update(): completed / billed work is never deleted.
        $isBilled = $item->status === 'completed'
            || (int) $item->invoiced_units > 0
            || in_array($item->billing_progress, [
                TreatmentPlanItem::PROGRESS_PARTIAL,
                TreatmentPlanItem::PROGRESS_COMPLETED,
                TreatmentPlanItem::PROGRESS_INVOICED,
            ], true);

        if ($isBilled) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove: this item is already completed or billed.',
            ], 422);
        }

        $plan = $item->plan;
        $item->delete();
        $plan->update(['total' => $plan->items()->sum('total')]);

        return response()->json(['success' => true, 'message' => 'Item removed.']);
    }

    // ── P2C10c: Redirect to patient profile with consultation context pre-loaded ──

    /**
     * GET /patients/{patient}/treatment-plans/from-consultation/{consultation}
     *
     * Called after "Save & Start Treatment Plan" on the consultation form.
     * Redirects to the patient profile treatment-plan tab with query params
     * so the Alpine component can auto-open + pre-fill the plan form.
     */
    public function createFromConsultation(Patient $patient, \App\Models\Consultation $consultation)
    {
        // Verify the consultation belongs to this patient
        abort_if($consultation->patient_id !== $patient->id, 404);

        return redirect()
            ->route('patients.show', $patient)
            ->with([
                'activeTab'            => 'treatment-plan',
                'from_consultation_id' => $consultation->id,
            ])
            ->withFragment('treatment-plan');
    }

    // ── AI: suggest treatment based on consultation findings ─────────────────

    public function aiSuggest(Request $request, Patient $patient): JsonResponse
    {
        $request->validate([
            'chief_complaint'      => ['nullable', 'string'],
            'examination_notes'    => ['nullable', 'string'],
            'radiographic_notes'   => ['nullable', 'string'],
            'diagnosis'            => ['nullable', 'string'],
        ]);

        $text = strtolower(implode(' ', array_filter([
            $request->chief_complaint,
            $request->examination_notes,
            $request->radiographic_notes,
            $request->diagnosis,
        ])));

        if (!$text) {
            return response()->json(['success' => false, 'message' => 'No consultation data found.'], 422);
        }

        $groups = [];

        // ── RCT ──
        if (preg_match('/pulp|rct|root canal|periapical|abscess|irreversible|necrosis|apical/', $text)) {
            $groups[] = [
                'problem'      => 'Pulpal / Periapical Pathology',
                'tooth_number' => $this->extractTooth($text),
                'options'      => [
                    ['treatment_name' => 'Root Canal- Posterior', 'option_rank' => 'best',        'brief_reason' => 'Pulp involvement — save the tooth', 'unit_price' => $this->price('Root Canal- Posterior')],
                    ['treatment_name' => 'Extraction',            'option_rank' => 'acceptable',  'brief_reason' => 'If tooth not restorable',            'unit_price' => $this->price('Extraction')],
                ],
            ];
        }

        // ── Crown ──
        if (preg_match('/crown|cap|post rct|after rct|zirconia|pfm/', $text)) {
            $groups[] = [
                'problem'      => 'Crown Restoration',
                'tooth_number' => $this->extractTooth($text),
                'options'      => [
                    ['treatment_name' => 'Crown Zirconia',  'option_rank' => 'best',       'brief_reason' => 'Best aesthetics and strength', 'unit_price' => $this->price('Crown Zirconia')],
                    ['treatment_name' => 'Crown PFM',       'option_rank' => 'acceptable', 'brief_reason' => 'Cost-effective alternative',   'unit_price' => $this->price('Crown PFM')],
                ],
            ];
        }

        // ── Filling ──
        if (preg_match('/caries|cavity|decay|filling|composite|restoration|carries/', $text)) {
            $groups[] = [
                'problem'      => 'Carious Lesion',
                'tooth_number' => $this->extractTooth($text),
                'options'      => [
                    ['treatment_name' => 'Composite Filing- 1 Surface', 'option_rank' => 'best',       'brief_reason' => 'Tooth-coloured restoration', 'unit_price' => $this->price('Composite Filing- 1 Surface')],
                    ['treatment_name' => 'GIC Filling',                  'option_rank' => 'acceptable', 'brief_reason' => 'Economical option',          'unit_price' => $this->price('GIC Filling')],
                ],
            ];
        }

        // ── Scaling ──
        if (preg_match('/calculus|tartar|scaling|plaque|gingivitis|gum|periodontal|bleeding/', $text)) {
            $groups[] = [
                'problem'      => 'Periodontal Disease',
                'tooth_number' => '',
                'options'      => [
                    ['treatment_name' => 'Scaling & Polishing', 'option_rank' => 'best',       'brief_reason' => 'Remove calculus and plaque', 'unit_price' => $this->price('Scaling & Polishing')],
                    ['treatment_name' => 'Root Planing',        'option_rank' => 'acceptable', 'brief_reason' => 'If deep pockets present',    'unit_price' => $this->price('Root Planing')],
                ],
            ];
        }

        // ── Implant ──
        if (preg_match('/missing|implant|edentulous|gap|space|replacement/', $text)) {
            $groups[] = [
                'problem'      => 'Missing Tooth',
                'tooth_number' => $this->extractTooth($text),
                'options'      => [
                    ['treatment_name' => 'Implant',         'option_rank' => 'best',        'brief_reason' => 'Best long-term replacement',   'unit_price' => $this->price('Implant')],
                    ['treatment_name' => 'Bridge (PFM)',    'option_rank' => 'acceptable',  'brief_reason' => 'Fixed but involves adjacent teeth', 'unit_price' => $this->price('Bridge (PFM)')],
                ],
            ];
        }

        // ── Extraction ──
        if (preg_match('/extract|remove|wisdom|impacted|mobile|grade [23]/', $text)) {
            $groups[] = [
                'problem'      => 'Extraction Required',
                'tooth_number' => $this->extractTooth($text),
                'options'      => [
                    ['treatment_name' => 'Extraction',          'option_rank' => 'best',       'brief_reason' => 'Tooth not restorable',         'unit_price' => $this->price('Extraction')],
                    ['treatment_name' => 'Surgical Extraction', 'option_rank' => 'acceptable', 'brief_reason' => 'If impacted or complex case',  'unit_price' => $this->price('Surgical Extraction')],
                ],
            ];
        }

        // ── Sensitivity / Desensitization ──
        if (preg_match('/sensitiv|sensitivity|cold|hot|sweet|thermal/', $text)) {
            $groups[] = [
                'problem'      => 'Tooth Sensitivity',
                'tooth_number' => $this->extractTooth($text),
                'options'      => [
                    ['treatment_name' => 'Desensitization', 'option_rank' => 'best',       'brief_reason' => 'Non-invasive first line',   'unit_price' => $this->price('Desensitization')],
                    ['treatment_name' => 'Fluoride Therapy', 'option_rank' => 'acceptable', 'brief_reason' => 'Strengthen enamel',        'unit_price' => $this->price('Fluoride Therapy')],
                ],
            ];
        }

        if (empty($groups)) {
            $groups[] = [
                'problem'      => 'General Assessment',
                'tooth_number' => '',
                'options'      => [
                    ['treatment_name' => 'Consultation', 'option_rank' => 'best', 'brief_reason' => 'Further evaluation needed', 'unit_price' => $this->price('Consultation')],
                ],
            ];
        }

        return response()->json([
            'success'    => true,
            'suggestion' => [
                'diagnosis_summary' => 'Based on clinical findings — please review and adjust as needed.',
                'plan_groups'       => $groups,
            ],
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    private function extractTooth(string $text): string
    {
        if (preg_match('/\b([1-4][1-8]|[5-8][1-5])\b/', $text, $m)) {
            return $m[1];
        }
        if (preg_match('/lower left|ll/i', $text))  return '36';
        if (preg_match('/lower right|lr/i', $text)) return '46';
        if (preg_match('/upper left|ul/i', $text))  return '26';
        if (preg_match('/upper right|ur/i', $text)) return '16';
        return '';
    }

    private function price(string $name): float
    {
        static $prices = null;
        if ($prices === null) {
            $prices = \App\Models\Treatment::pluck('default_price', 'name')->toArray();
        }
        return (float)($prices[$name] ?? 0);
    }
    /**
     * F4 — AUTHORIZATION PARITY WITH THE API.
     *
     * Every plan-scoped web action binds a plan straight from the URL. Module
     * permission middleware answers "may this user work with treatment plans
     * at all", never "is this particular plan theirs" — so the mobile surface
     * checked ownership (Api\V1\TreatmentPlanController::findPlan) and the web
     * surface did not.
     *
     * A missing branch on either side is treated as "not scoped" rather than
     * "denied", matching how BranchScope already behaves; tightening that is a
     * tenancy decision for the platform, not something to change here.
     */
    private function assertPlanAccessible(TreatmentPlan $plan): void
    {
        $plan->loadMissing('patient');

        abort_if(! $plan->patient, 404, 'Treatment plan not found.');

        $planBranch = $plan->patient->branch_id;
        $userBranch = Auth::user()?->branch_id;

        if (is_null($planBranch) || is_null($userBranch)) {
            return;
        }

        abort_if((int) $planBranch !== (int) $userBranch, 404, 'Treatment plan not found.');
    }

    private function syncItems(TreatmentPlan $plan, array $items, float $overallDiscPct): void
    {
        // Only write material_variants if the column exists (migration may not have run yet)
        static $hasVariantsCol = null;
        if ($hasVariantsCol === null) {
            $hasVariantsCol = \Illuminate\Support\Facades\Schema::hasColumn('treatment_plan_items', 'material_variants');
        }

        foreach ($items as $idx => $row) {
            // F3 — THE PLAN OWNS ITS ITEMS.
            // Canonical Treatment Lifecycle V1 §14: only the owner writes a
            // fact. Looking an item up globally allowed a posted id belonging
            // to another patient's plan to be re-parented into this one, with
            // its price and description overwritten. Scoping the lookup to the
            // plan means an unknown id creates a new item here instead.
            $item = isset($row['id'])
                ? $plan->items()->whereKey($row['id'])->first() ?? new TreatmentPlanItem()
                : new TreatmentPlanItem();

            $data = [
                'treatment_plan_id' => $plan->id,
                'treatment_id'      => $row['treatment_id']  ?? null,
                'tooth_number'      => $row['tooth_number']  ?? null,
                'treatment_name'    => $row['treatment_name'],
                'unit_price'        => (float)($row['unit_price']  ?? 0),
                'units'             => (int)($row['units']         ?? 1),
                'disc_pct'          => (float)($row['disc_pct']    ?? $overallDiscPct),
                'gst_pct'           => (float)($row['gst_pct']     ?? 0),
                'option_rank'       => $row['option_rank']  ?? 'best',
                // F3 — item lifecycle state is NEVER client-supplied. It was
                // read straight from the request, so a caller could mark an
                // item completed with no visit behind it, and that value then
                // decided whether the item was protected from deletion. An
                // existing item keeps whatever it already has; a new one starts
                // pending.
                'status'            => $item->exists ? $item->status : 'pending',
                'notes'             => $row['notes']         ?? null,
                'sort_order'        => $idx,
                'aocp_applied'      => (bool)($row['aocp_applied'] ?? false),
                'consent_required'  => (bool)($row['consent_required'] ?? false),
            ];

            if ($hasVariantsCol) {
                $data['material_variants'] = isset($row['variants']) && is_array($row['variants']) && count($row['variants']) > 0
                    ? $row['variants']
                    : null;
            }

            $item->fill($data);

            $item->recalculate();
            $item->save();
        }
    }

    private function formatPlan(TreatmentPlan $plan): array
    {
        return [
            'id'                 => $plan->id,
            'plan_uuid'          => $plan->plan_uuid,
            'plan_name'          => $plan->plan_name,
            'display_order'      => (int)$plan->display_order,
            'status'             => $plan->status,
            'is_accepted'        => (bool)$plan->accepted_at,
            // "Presented" is the CLINICAL fact and nothing else. An Opportunity
            // row is PRE/commercial state: it may only ever be a PROJECTION of
            // presentation, never evidence of it. Historical plans therefore
            // read as not presented until someone records the clinical fact.
            'is_presented'       => $plan->presented_at !== null,
            'presented_at'       => $plan->presented_at?->format('d M Y'),
            'decision_pending'   => $plan->is_decision_pending,
            // Slice 2.4e — clinical progress comes from the ONE canonical
            // service. This controller consumes it; it never derives.
            'progress'           => app(\App\Services\Clinical\DerivedProgressService::class)
                                        ->deriveTreatmentPlanProgress($plan)->progress->label(),
            'accepted_at'        => $plan->accepted_at?->format('d M Y'),
            'total'              => (float)$plan->total,
            'consultation_id'    => $plan->consultation_id,
            'doctor_id'          => $plan->doctor_id,
            'plan_date'          => $plan->plan_date?->format('Y-m-d'),
            'estimated_duration' => $plan->estimated_duration,
            'visit_count'        => $plan->visit_count ? (int)$plan->visit_count : null,
            'doctor_notes'       => $plan->doctor_notes,
            'created_by_name'    => $plan->creator?->name,
            'created_at'         => $plan->created_at?->format('d M Y'),
            // Clinical procedure list — no billing fields
            'items'              => $plan->items->map(fn($i) => [
                'id'                => $i->id,
                'treatment_id'      => $i->treatment_id,
                'tooth_number'      => $i->tooth_number,
                'units'             => (int)($i->units ?? 1),
                'treatment_name'    => $i->treatment_name,
                'unit_price'        => (float)$i->unit_price,
                'total'             => (float)$i->total,
                'notes'             => $i->notes,
                'variants'          => $i->material_variants ?? [],
                'consent_required'  => (bool)$i->consent_required,
            ])->values(),
        ];
    }
}
