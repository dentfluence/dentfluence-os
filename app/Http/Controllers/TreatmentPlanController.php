<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\Treatment;
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
        $plans = TreatmentPlan::with(['items', 'patient', 'consultation.doctor', 'creator'])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn($p) => array_search($p->id, $ids))
            ->values();

        abort_if($plans->isEmpty(), 404, 'Plans not found.');

        // Assign A/B/C internal letters
        $letters = ['A', 'B', 'C', 'D', 'E'];
        foreach ($plans as $idx => $plan) {
            $plan->plan_letter = $letters[$idx] ?? ($idx + 1);
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

    // ── Return items for a single plan (used by visit form AJAX) ────────────

    public function getItems(TreatmentPlan $plan): JsonResponse
    {
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
            ->with(['items', 'creator'])
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
            'estimated_duration' => ['nullable', 'string', 'max:50'],
            'visit_count'        => ['nullable', 'integer', 'min:1'],
            'doctor_notes'       => ['nullable', 'string'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.tooth_number'   => ['nullable', 'string', 'max:100'],
            'items.*.treatment_name' => ['required', 'string', 'max:150'],
            'items.*.unit_price'     => ['required', 'numeric', 'min:0'],
            'items.*.units'          => ['nullable', 'integer', 'min:1'],
            'items.*.notes'          => ['nullable', 'string'],
        ]);

        $plan = DB::transaction(function () use ($request, $patient) {
            // Auto-name: "Treatment Option 1", "Option 2" etc. per consultation
            $existingCount = $patient->treatmentPlans()
                ->when($request->consultation_id, fn($q) => $q->where('consultation_id', $request->consultation_id))
                ->count();

            $plan = TreatmentPlan::create([
                'patient_id'         => $patient->id,
                'consultation_id'    => $request->consultation_id,
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

        return response()->json([
            'success' => true,
            'message' => 'Treatment option created.',
            'plan'    => $this->formatPlan($plan),
        ]);
    }

    // ── Update a treatment option ─────────────────────────────────────────────

    public function update(Request $request, TreatmentPlan $plan): JsonResponse
    {
        $request->validate([
            'plan_name'          => ['nullable', 'string', 'max:100'],
            'estimated_duration' => ['nullable', 'string', 'max:50'],
            'visit_count'        => ['nullable', 'integer', 'min:1'],
            'doctor_notes'       => ['nullable', 'string'],
            'status'             => ['nullable', 'in:pending,ongoing,completed,cancelled'],
            'items'              => ['nullable', 'array'],
            'items.*.id'             => ['nullable', 'exists:treatment_plan_items,id'],
            'items.*.tooth_number'   => ['nullable', 'string', 'max:100'],
            'items.*.treatment_name' => ['required_with:items', 'string', 'max:150'],
            'items.*.unit_price'     => ['required_with:items', 'numeric', 'min:0'],
            'items.*.units'          => ['nullable', 'integer', 'min:1'],
            'items.*.notes'          => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $plan) {
            $plan->update(array_filter([
                'plan_name'          => $request->plan_name,
                'estimated_duration' => $request->estimated_duration,
                'visit_count'        => $request->visit_count,
                'doctor_notes'       => $request->doctor_notes,
                'status'             => $request->status,
            ], fn($v) => !is_null($v)));

            if ($request->has('items')) {
                $keptIds = collect($request->items)->pluck('id')->filter()->all();
                $plan->items()->whereNotIn('id', $keptIds)->delete();
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

    public function accept(TreatmentPlan $plan): JsonResponse
    {
        $plan->update([
            'accepted_at' => now(),
            'status'      => 'ongoing',
        ]);

        $plan->load(['items', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Treatment option accepted.',
            'plan'    => $this->formatPlan($plan),
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
        // Reason is mandatory — it's recorded in the log.
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        // Nothing to do if it isn't accepted.
        if (is_null($plan->accepted_at)) {
            return response()->json([
                'success' => false,
                'message' => 'This plan is not accepted, so there is nothing to revert.',
            ], 422);
        }

        // Billing guard — refuse if invoices already exist against this plan.
        if ($plan->invoices()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot revert: this plan already has invoices/billing against it.',
            ], 422);
        }

        $plan->load('patient');

        // Flip back to the un-accepted (pending) state.
        $plan->update([
            'accepted_at' => null,
            'status'      => 'pending',
        ]);

        // Record the revert in the staff activity log (with the reason).
        // Note: the log's `note` column is varchar(255), so cap the message.
        $note = sprintf(
            'Reverted treatment plan #%d (%s) for patient %s. Reason: %s',
            $plan->id,
            $plan->plan_name,
            $plan->patient?->name ?? ('#' . $plan->patient_id),
            $request->input('reason')
        );

        \App\Models\StaffActivityLog::record(
            Auth::id(),                       // acting user
            'tp_reverted',                    // action (fits varchar(40))
            'accepted',                       // old value
            'pending',                        // new value
            mb_substr($note, 0, 255)          // note (capped to column length)
        );

        $plan->load(['items', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Treatment option acceptance reverted.',
            'plan'    => $this->formatPlan($plan),
        ]);
    }

    // ── Delete a plan ────────────────────────────────────────────────────────

    public function destroy(TreatmentPlan $plan): JsonResponse
    {
        $plan->delete();

        return response()->json(['success' => true, 'message' => 'Treatment plan deleted.']);
    }

    // ── Delete a single item ─────────────────────────────────────────────────

    public function destroyItem(TreatmentPlanItem $item): JsonResponse
    {
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
    private function syncItems(TreatmentPlan $plan, array $items, float $overallDiscPct): void
    {
        // Only write material_variants if the column exists (migration may not have run yet)
        static $hasVariantsCol = null;
        if ($hasVariantsCol === null) {
            $hasVariantsCol = \Illuminate\Support\Facades\Schema::hasColumn('treatment_plan_items', 'material_variants');
        }

        foreach ($items as $idx => $row) {
            $item = isset($row['id'])
                ? TreatmentPlanItem::find($row['id']) ?? new TreatmentPlanItem()
                : new TreatmentPlanItem();

            $data = [
                'treatment_plan_id' => $plan->id,
                'tooth_number'      => $row['tooth_number']  ?? null,
                'treatment_name'    => $row['treatment_name'],
                'unit_price'        => (float)($row['unit_price']  ?? 0),
                'units'             => (int)($row['units']         ?? 1),
                'disc_pct'          => (float)($row['disc_pct']    ?? $overallDiscPct),
                'gst_pct'           => (float)($row['gst_pct']     ?? 0),
                'option_rank'       => $row['option_rank']  ?? 'best',
                'status'            => $row['status']        ?? 'pending',
                'notes'             => $row['notes']         ?? null,
                'sort_order'        => $idx,
                'aocp_applied'      => (bool)($row['aocp_applied'] ?? false),
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
            'accepted_at'        => $plan->accepted_at?->format('d M Y'),
            'total'              => (float)$plan->total,
            'consultation_id'    => $plan->consultation_id,
            'estimated_duration' => $plan->estimated_duration,
            'visit_count'        => $plan->visit_count ? (int)$plan->visit_count : null,
            'doctor_notes'       => $plan->doctor_notes,
            'created_by_name'    => $plan->creator?->name,
            'created_at'         => $plan->created_at?->format('d M Y'),
            // Clinical procedure list — no billing fields
            'items'              => $plan->items->map(fn($i) => [
                'id'             => $i->id,
                'tooth_number'   => $i->tooth_number,
                'units'          => (int)($i->units ?? 1),
                'treatment_name' => $i->treatment_name,
                'unit_price'     => (float)$i->unit_price,
                'total'          => (float)$i->total,
                'notes'          => $i->notes,
                'variants'       => $i->material_variants ?? [],
            ])->values(),
        ];
    }
}
