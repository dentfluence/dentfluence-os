<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\AppSetting;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'items.*.unit_price'     => ['required', 'numeric', 'min:0'],
            'items.*.units'          => ['nullable', 'integer', 'min:1'],
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
            'items.*.unit_price'     => ['required_with:items', 'numeric', 'min:0'],
            'items.*.units'          => ['nullable', 'integer', 'min:1'],
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
                $p->items()->whereNotIn('id', $keep)->delete();
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

        $p->update(['accepted_at' => now(), 'status' => 'ongoing']);

        return $this->success($this->payload($p->fresh(['items', 'creator'])),
            'Treatment option accepted.');
    }

    public function revert(Request $request, $plan): JsonResponse
    {
        $p = $this->findPlan($request, $plan);
        if ($p instanceof JsonResponse) return $p;

        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        if (is_null($p->accepted_at)) {
            return $this->error('This plan is not accepted.', [], 422);
        }

        $p->update(['accepted_at' => null, 'status' => 'pending']);

        return $this->success($this->payload($p->fresh(['items', 'creator'])),
            'Acceptance reverted.');
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
            'id', 'branch_id', 'name', 'patient_id', 'phone', 'gender', 'age_years');

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
            'items'              => $plan->items->map(fn ($i) => [
                'id'             => $i->id,
                'tooth_number'   => $i->tooth_number,
                'treatment_name' => $i->treatment_name,
                'unit_price'     => (float) $i->unit_price,
                'units'          => (int) ($i->units ?? 1),
                'total'          => (float) $i->total,
                'notes'          => $i->notes,
            ])->values(),
        ];

        if ($withClinic) {
            $cs = AppSetting::where('group', 'clinic')->pluck('value', 'key');
            $out['clinic'] = [
                'name'    => $cs->get('name') ?? $cs->get('clinic_name') ?? config('app.name'),
                'phone'   => $cs->get('phone') ?? $cs->get('contact'),
                'address' => $cs->get('address'),
            ];
            $out['patient'] = [
                'name'       => $plan->patient->name,
                'patient_id' => $plan->patient->patient_id,
                'phone'      => $plan->patient->phone,
                'gender'     => $plan->patient->gender,
                'age'        => $plan->patient->age_years,
            ];
        }

        return $out;
    }
}
