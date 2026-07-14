<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\AppSetting;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Services\Billing\TreatmentPlanBillingService;
use App\Services\TreatmentPlan\TreatmentPlanAcceptanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * TreatmentPlanController (API v1)
 * --------------------------------
 * Mirrors the web treatment-plan flow: create options with line items,
 * edit, accept / revert, and a printable detail. Branch-scoped via patient.
 */
class TreatmentPlanController extends ApiController
{
    /** Active treatments (master) for the line-item picker. */
    public function treatments(Request $request): JsonResponse
    {
        $rows = Treatment::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'default_price'])
            ->map(fn ($t) => [
                'id'    => $t->id,
                'name'  => $t->name,
                'price' => (float) $t->default_price,
            ]);

        return $this->success($rows, '');
    }

    /** Full plan (with items) for view / print. */
    public function show(Request $request, $plan): JsonResponse
    {
        $p = $this->findPlan($request, $plan, withClinic: true);
        if ($p instanceof JsonResponse) return $p;

        return $this->success($this->payload($p, withClinic: true), '');
    }

    public function store(Request $request, $patient): JsonResponse
    {
        $pt = Patient::where('branch_id', $request->user()->branch_id)
            ->whereKey($patient)->first();
        if (! $pt) return $this->error('Patient not found.', [], 404);

        $data = $request->validate([
            'plan_name'              => ['nullable', 'string', 'max:100'],
            'consultation_id'        => ['nullable', 'integer', 'exists:consultations,id'],
            'estimated_duration'     => ['nullable', 'string', 'max:50'],
            'visit_count'            => ['nullable', 'integer', 'min:1'],
            'doctor_notes'           => ['nullable', 'string'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.tooth_number'   => ['nullable', 'string', 'max:100'],
            'items.*.treatment_name' => ['required', 'string', 'max:150'],
            'items.*.treatment_id'   => ['nullable', 'integer', 'exists:treatments,id'],
            'items.*.unit_price'     => ['required', 'numeric', 'min:0'],
            'items.*.units'          => ['nullable', 'integer', 'min:1'],
            'items.*.consent_required' => ['nullable', 'boolean'],
            'items.*.notes'          => ['nullable', 'string'],
        ]);

        $plan = DB::transaction(function () use ($data, $pt, $request) {
            $count = $pt->treatmentPlans()
                ->when($data['consultation_id'] ?? null,
                    fn ($q) => $q->where('consultation_id', $data['consultation_id']))
                ->count();

            $plan = TreatmentPlan::create([
                'patient_id'         => $pt->id,
                'consultation_id'    => $data['consultation_id'] ?? null,
                'plan_name'          => $data['plan_name'] ?? ('Treatment Option ' . ($count + 1)),
                'display_order'      => $count + 1,
                'status'             => 'pending',
                'created_by'         => $request->user()->id,
                'estimated_duration' => $data['estimated_duration'] ?? null,
                'visit_count'        => $data['visit_count'] ?? null,
                'doctor_notes'       => $data['doctor_notes'] ?? null,
            ]);
            $this->syncItems($plan, $data['items']);
            $plan->update(['total' => $plan->items()->sum('total')]);
            return $plan;
        });

        return $this->success($this->payload($plan->fresh(['items', 'creator'])),
            'Treatment option created.', 201);
    }

    public function update(Request $request, $plan): JsonResponse
    {
        $p = $this->findPlan($request, $plan);
        if ($p instanceof JsonResponse) return $p;

        $data = $request->validate([
            'plan_name'              => ['nullable', 'string', 'max:100'],
            'estimated_duration'     => ['nullable', 'string', 'max:50'],
            'visit_count'            => ['nullable', 'integer', 'min:1'],
            'doctor_notes'           => ['nullable', 'string'],
            'status'                 => ['nullable', 'in:pending,ongoing,completed,cancelled'],
            'items'                  => ['nullable', 'array'],
            'items.*.id'             => ['nullable', 'integer'],
            'items.*.tooth_number'   => ['nullable', 'string', 'max:100'],
            'items.*.treatment_name' => ['required_with:items', 'string', 'max:150'],
            'items.*.treatment_id'   => ['nullable', 'integer', 'exists:treatments,id'],
            'items.*.unit_price'     => ['required_with:items', 'numeric', 'min:0'],
            'items.*.units'          => ['nullable', 'integer', 'min:1'],
            'items.*.consent_required' => ['nullable', 'boolean'],
            'items.*.notes'          => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $p) {
            $p->update(array_filter([
                'plan_name'          => $data['plan_name'] ?? null,
                'estimated_duration' => $data['estimated_duration'] ?? null,
                'visit_count'        => $data['visit_count'] ?? null,
                'doctor_notes'       => $data['doctor_notes'] ?? null,
                'status'             => $data['status'] ?? null,
            ], fn ($v) => ! is_null($v)));

            if (array_key_exists('items', $data)) {
                $keep = collect($data['items'])->pluck('id')->filter()->all();

                // Never delete completed / already-billed items (web parity —
                // a revision re-sends only the pending rows, so the old blanket
                // delete destroyed finished work and its invoice linkage).
                $protected = $p->items()
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

                $p->items()->whereNotIn('id', array_merge($keep, $protected))->delete();
                $this->syncItems($p, $data['items']);
                $p->update(['total' => $p->items()->sum('total')]);
            }
        });

        return $this->success($this->payload($p->fresh(['items', 'creator'])),
            'Treatment option updated.');
    }

    public function accept(Request $request, $plan): JsonResponse
    {
        $p = $this->findPlan($request, $plan);
        if ($p instanceof JsonResponse) return $p;

        // This path previously ONLY flipped the status — it silently skipped
        // the Timeline log and the follow-up Opportunity that the web path
        // creates, so a plan accepted on mobile produced different records to
        // the same plan accepted at the desk. Now both go through the shared
        // acceptance service.
        $p = app(TreatmentPlanAcceptanceService::class)
            ->accept($p, $request->user(), via: 'mobile');

        return $this->success($this->payload($p->fresh(['items', 'creator'])),
            'Treatment option accepted.');
    }

    public function revert(Request $request, $plan): JsonResponse
    {
        $p = $this->findPlan($request, $plan);
        if ($p instanceof JsonResponse) return $p;

        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        // Shared brain (2026-07-14) — this path previously flipped the status
        // with NO billing guard (a billed plan could be un-accepted from
        // mobile) and NO staff-activity audit. Same door as web now.
        try {
            $p = app(TreatmentPlanAcceptanceService::class)
                ->revert($p, $request->input('reason'), $request->user(), 'mobile');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), [], 422);
        }

        return $this->success($this->payload($p->fresh(['items', 'creator'])),
            'Acceptance reverted.');
    }

    /**
     * Pending teeth available for partial billing, grouped by plan item.
     * Mirrors web's /billing/from-plan/{plan} GET screen data.
     */
    public function billableTeeth(Request $request, $plan): JsonResponse
    {
        $p = $this->findPlan($request, $plan);
        if ($p instanceof JsonResponse) return $p;

        if (is_null($p->accepted_at)) {
            return $this->error('This plan must be accepted before billing.', [], 422);
        }

        app(TreatmentPlanBillingService::class)->ensurePlanTeeth($p);

        $items = $p->items()->with(['teeth' => fn ($q) => $q->orderBy('id')])
            ->orderBy('sort_order')->get()
            ->map(fn (TreatmentPlanItem $i) => [
                'id'              => $i->id,
                'treatment_name'  => $i->treatment_name,
                'tooth_number'    => $i->tooth_number,
                'unit_price'      => (float) $i->unit_price,
                'billing_progress'=> $i->billing_progress,
                'teeth'           => $i->teeth->map(fn ($t) => [
                    'id'           => $t->id,
                    'tooth_number' => $t->tooth_number,
                    'status'       => $t->status,
                ])->values(),
            ])
            ->filter(fn ($row) => collect($row['teeth'])->contains('status', 'pending'))
            ->values();

        return $this->success([
            'plan_id' => $p->id,
            'items'   => $items,
        ], '');
    }

    /** Create an invoice from a selected subset of pending teeth. */
    public function bill(Request $request, $plan): JsonResponse
    {
        $p = $this->findPlan($request, $plan);
        if ($p instanceof JsonResponse) return $p;

        $data = $request->validate([
            'tooth_ids'   => ['required', 'array', 'min:1'],
            'tooth_ids.*' => ['integer'],
        ]);

        try {
            $invoice = app(TreatmentPlanBillingService::class)
                ->createInvoiceFromSelection($p, $data['tooth_ids'], $request->user()->id);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), $e->errors(), 422);
        }

        return $this->success([
            'invoice_id'     => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'total_amount'   => (float) $invoice->total_amount,
        ], 'Invoice created from plan.', 201);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function syncItems(TreatmentPlan $plan, array $items): void
    {
        foreach ($items as $idx => $row) {
            $item = isset($row['id'])
                ? (TreatmentPlanItem::find($row['id']) ?? new TreatmentPlanItem())
                : new TreatmentPlanItem();

            $item->fill([
                'treatment_plan_id' => $plan->id,
                'tooth_number'      => $row['tooth_number'] ?? null,
                'treatment_name'    => $row['treatment_name'],
                // Master-treatment link + consent flag — same columns the
                // web form writes (2026-07-14 parity; were dropped here).
                'treatment_id'      => $row['treatment_id'] ?? null,
                'consent_required'  => (bool) ($row['consent_required'] ?? false),
                'unit_price'        => (float) ($row['unit_price'] ?? 0),
                'units'             => (int) ($row['units'] ?? 1),
                'disc_pct'          => 0,
                'gst_pct'           => 0,
                'option_rank'       => 'best',
                'status'            => 'pending',
                'notes'             => $row['notes'] ?? null,
                'sort_order'        => $idx,
            ]);
            $item->recalculate();
            $item->save();
        }
    }

    /** Returns the plan (branch-checked) or a JsonResponse error. */
    private function findPlan(Request $request, $id, bool $withClinic = false)
    {
        $with = ['items' => fn ($q) => $q->orderBy('sort_order'), 'creator:id,name'];
        $with['patient'] = fn ($q) => $q->select(
            'id', 'branch_id', 'name', 'patient_id', 'phone', 'gender', 'age_years',
            'address', 'area', 'city');
        if ($withClinic) {
            $with['consultation.doctor'] = fn ($q) => $q;
        }

        $plan = TreatmentPlan::with($with)->whereKey($id)->first();

        if (! $plan || ! $plan->patient ||
            (int) $plan->patient->branch_id !== (int) $request->user()->branch_id) {
            return $this->error('Treatment plan not found.', [], 404);
        }
        return $plan;
    }

    private function payload(TreatmentPlan $plan, bool $withClinic = false): array
    {
        $out = [
            'id'                 => $plan->id,
            'plan_name'          => $plan->plan_name,
            'status'             => $plan->status,
            'is_accepted'        => (bool) $plan->accepted_at,
            'accepted_at'        => $plan->accepted_at?->format('d M Y'),
            'total'              => (float) $plan->total,
            'estimated_duration' => $plan->estimated_duration,
            'visit_count'        => $plan->visit_count ? (int) $plan->visit_count : null,
            'doctor_notes'       => $plan->doctor_notes,
            'created_by'         => $plan->creator?->name,
            'created_at'         => $plan->created_at?->format('d M Y'),
            'consultation_id'    => $plan->consultation_id,
            'items'              => $plan->items->map(fn ($i) => [
                'id'             => $i->id,
                'tooth_number'   => $i->tooth_number,
                'treatment_name' => $i->treatment_name,
                'unit_price'     => (float) $i->unit_price,
                'units'          => (int) ($i->units ?? 1),
                'total'          => (float) $i->total,
                'disc_amount'    => (float) $i->disc_amount,
                'gst_amount'     => (float) $i->gst_amount,
                'net_amount'     => (float) $i->net_amount,
                'notes'          => $i->notes,
            ])->values(),
        ];

        if ($withClinic) {
            // Same settings keys the web print-letterhead partial reads —
            // the previous version of this payload used the wrong keys
            // ('name'/'phone'/'address' instead of 'clinic_name' etc.),
            // which silently left the print clinic block empty on mobile.
            $clinic = AppSetting::where('group', 'clinic')->pluck('value', 'key');
            $print  = AppSetting::where('group', 'print')->pluck('value', 'key');
            $headerType = $print->get('print_header_type') ?? 'plain';
            // Plain paper = pre-printed stationery already carrying clinic
            // identity, so we don't re-print the clinic name/logo (matches
            // web's $showClinic logic in treatment-plans/print.blade.php).
            $showClinic = $headerType !== 'plain';

            $out['clinic'] = [
                'name'           => $clinic->get('clinic_name') ?? config('app.name'),
                'phone'          => $clinic->get('clinic_phone'),
                'address'        => $clinic->get('clinic_address'),
                'header_type'    => $headerType,
                'show_identity'  => $showClinic,
                'logo_url'       => ($headerType === 'logo' && $clinic->get('clinic_logo'))
                    ? \Illuminate\Support\Facades\Storage::url($clinic->get('clinic_logo'))
                    : null,
                'letterhead_url' => ($headerType === 'letterhead' && $print->get('print_letterhead'))
                    ? \Illuminate\Support\Facades\Storage::url($print->get('print_letterhead'))
                    : null,
            ];

            $out['patient'] = [
                'name'       => $plan->patient->name,
                'patient_id' => $plan->patient->patient_id,
                'phone'      => $plan->patient->phone,
                'gender'     => $plan->patient->gender,
                'age'        => $plan->patient->age_years,
                'address'    => trim(implode(', ', array_filter([
                    $plan->patient->address ?? null,
                    $plan->patient->area ?? null,
                    $plan->patient->city ?? null,
                ])), ', '),
            ];

            // Doctor block — pulled from the linked consultation, exactly
            // like web's treatment-plans/print.blade.php ($consultation?->doctor).
            $doctor = $plan->consultation?->doctor;
            $out['doctor'] = $doctor ? [
                'name'                => $doctor->doctor_name,
                'designation'         => $doctor->designation,
                'registration_number' => $doctor->registration_number,
            ] : null;

            // Validity window — matches web's 15-day estimate window, dated
            // from the linked consultation (falls back to the plan's own
            // created_at when there's no linked consultation).
            $planDate = $plan->consultation?->consultation_date
                ? \Carbon\Carbon::parse($plan->consultation->consultation_date)
                : $plan->created_at;
            $out['plan_date']   = $planDate?->format('d M Y');
            $out['valid_days']  = 15;
            $out['valid_until'] = $planDate?->copy()->addDays(15)->format('d M Y');
        }

        return $out;
    }
}
