<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\LabCase;
use App\Models\LabVendor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\TreatmentVisit;
use App\Models\User;
use App\Services\TreatmentVisitService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TreatmentVisitController (API v1)
 * ---------------------------------
 * Mobile create/update/delete for treatment visits. Uses the SAME
 * TreatmentVisitService as the web, so a visit saved on the phone fires the
 * identical side-effects (billing prompt, draft lab case, 6-month recall task).
 *
 *   GET    /api/v1/patients/{patient}/visits/form-options
 *   POST   /api/v1/patients/{patient}/visits
 *   PUT    /api/v1/visits/{visit}
 *   DELETE /api/v1/visits/{visit}
 *
 * Everything is branch-scoped: a visit/patient from another branch returns an
 * enveloped 404.
 */
class TreatmentVisitController extends ApiController
{
    public function __construct(private TreatmentVisitService $service)
    {
    }

    public function store(Request $request, $patient): JsonResponse
    {
        $p = $this->findPatient($request, $patient);

        $data = $request->validate(TreatmentVisitService::rules());
        // Doctor defaults to the logged-in user when the client omits it.
        $data['doctor_id'] = $data['doctor_id'] ?? $request->user()->id;

        $visit = $this->service->create($p, $data);

        return $this->success($this->service->format($visit), 'Treatment visit saved.', 201);
    }

    public function update(Request $request, $visit): JsonResponse
    {
        $v = $this->findVisit($request, $visit);

        $data = $request->validate(TreatmentVisitService::rules());
        $data['doctor_id'] = $data['doctor_id'] ?? $v->doctor_id ?? $request->user()->id;

        $v = $this->service->update($v, $data);

        return $this->success($this->service->format($v), 'Treatment visit updated.');
    }

    /**
     * Full visit detail for view / edit prefill. Uses the service's format()
     * (same shape the web returns) and merges the vitals columns, which format()
     * doesn't currently include, so the mobile edit form round-trips cleanly.
     */
    public function show(Request $request, $visit): JsonResponse
    {
        $v = $this->findVisit($request, $visit);
        $v->load(['doctor', 'visitItems', 'patient']);

        $out = $this->service->format($v);
        $out += [
            'bp_systolic'      => $v->bp_systolic,
            'bp_diastolic'     => $v->bp_diastolic,
            'pulse_rate'       => $v->pulse_rate,
            'spo2'             => $v->spo2,
            'temperature'      => $v->temperature,
            'blood_sugar'      => $v->blood_sugar,
            'blood_sugar_type' => $v->blood_sugar_type,
            'weight'           => $v->weight,
            'vitals_notes'     => $v->vitals_notes,
        ];

        // Print-only extras (clinic letterhead + patient + doctor block) —
        // same settings keys / shape as TreatmentPlanController::payload(),
        // so the mobile visit case-sheet PDF mirrors the web print exactly.
        $clinic = \App\Models\AppSetting::where('group', 'clinic')->pluck('value', 'key');
        $print  = \App\Models\AppSetting::where('group', 'print')->pluck('value', 'key');
        $headerType = $print->get('print_header_type') ?? 'plain';
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

        $p = $v->patient;
        $out['patient'] = $p ? [
            'name'       => $p->name,
            'patient_id' => $p->patient_id,
            'phone'      => $p->phone,
            'gender'     => $p->gender,
            'age'        => $p->age_years,
            'address'    => trim(implode(', ', array_filter([
                $p->address ?? null, $p->area ?? null, $p->city ?? null,
            ])), ', '),
        ] : null;

        $out['doctor'] = $v->doctor ? [
            'name'                => $v->doctor->doctor_name,
            'designation'         => $v->doctor->designation,
            'registration_number' => $v->doctor->registration_number,
        ] : null;

        return $this->success($out, '');
    }

    public function destroy(Request $request, $visit): JsonResponse
    {
        $v = $this->findVisit($request, $visit);
        $v->delete();

        return $this->success(null, 'Treatment visit deleted.');
    }

    /**
     * Everything the mobile form needs to render: doctors, this patient's
     * treatment plans (+ items), lab vendors, the treatment catalogue + their
     * stage maps, and the lab work-category list.
     */
    public function formOptions(Request $request, $patient): JsonResponse
    {
        $p = $this->findPatient($request, $patient);

        $doctors = User::orderBy('name')->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->values();

        // Only ACCEPTED plans (patient said yes -> accepted_at is set) should
        // surface in the visit form — same rule as web's treatment-visits-tab.
        // Pending/un-accepted options stay hidden here.
        $plans = $p->treatmentPlans()
            ->whereNotNull('accepted_at')
            ->with('items')
            ->latest()
            ->get()
            ->map(fn ($plan) => [
                'id'     => $plan->id,
                'name'   => $plan->plan_name,
                'status' => $plan->status,
                'items'  => $plan->items->map(fn ($it) => [
                    'id'             => $it->id,
                    'treatment_name' => $it->treatment_name,
                    'tooth_number'   => $it->tooth_number,
                    'unit_price'     => (float) $it->unit_price,
                    'status'         => $it->status,
                    'material_variants' => $it->material_variants ?? [],
                ])->values(),
            ])
            ->values();

        $labVendors = LabVendor::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($v) => ['id' => $v->id, 'name' => $v->name])
            ->values();

        // Appointments: upcoming + last 30 days, for the "Link Appointment"
        // dropdown — matches web's $appointmentsJson exactly.
        $appointments = $p->appointments()
            ->where('appointment_date', '>=', now()->subDays(30))
            ->with('treatmentCategory:id,name')
            ->orderByDesc('appointment_date')
            ->get()
            ->map(fn ($a) => [
                'id'         => $a->id,
                'date'       => $a->appointment_date?->format('Y-m-d'),
                'label'      => trim(
                    ($a->appointment_date?->format('d M Y') ?? '') .
                    ($a->appointment_time ? ' - ' . \Carbon\Carbon::parse($a->appointment_time)->format('h:i A') : '') .
                    ($a->treatmentCategory ? ' - ' . $a->treatmentCategory->name : '')
                ),
                'doctor_id'  => (string) ($a->doctor_id ?? ''),
                'status'     => $a->status,
            ])
            ->values();

        // Treatment -> lab work-category map, only for treatments flagged
        // needs_lab. Drives the "Lab Case Required" prompt's auto-detect —
        // matches web's TV_LAB_TREATMENTS.
        $labColsExist = \Illuminate\Support\Facades\Schema::hasColumn('treatments', 'needs_lab');
        $labTreatments = $labColsExist
            ? Treatment::where('is_active', true)->where('needs_lab', true)
                ->get(['name', 'lab_work_category'])
                ->keyBy('name')
                ->map(fn ($t) => ['work_category' => $t->lab_work_category ?? ''])
            : (object) [];

        // Implant catalog — stock-linked fixture/component picker for the
        // Implant specialty sub-form. Selecting one deducts real inventory.
        $implantCatalog = \App\Models\Inventory\ImplantCatalog::active()
            ->with('inventoryItem.stocks')
            ->orderBy('brand')
            ->get();
        $implantFixtures = $implantCatalog->where('component_type', 'fixture')
            ->map(fn ($c) => [
                'id'    => $c->id,
                'label' => $c->getFullName(),
                'stock' => $c->inventoryItem ? (float) $c->inventoryItem->total_stock : null,
            ])->values();
        $implantAccessories = $implantCatalog->where('component_type', '!=', 'fixture')
            ->map(fn ($c) => [
                'id'    => $c->id,
                'label' => $c->getComponentTypeLabel() . ' — ' . $c->brand,
                'stock' => $c->inventoryItem ? (float) $c->inventoryItem->total_stock : null,
            ])->values();

        return $this->success([
            'current_doctor_id'  => $request->user()->id,
            'doctors'            => $doctors,
            'treatment_plans'    => $plans,
            'lab_vendors'        => $labVendors,
            'appointments'       => $appointments,
            // Treatment names for the autocomplete, plus the per-treatment stage maps.
            'treatments'         => Treatment::orderBy('name')->pluck('name')->values(),
            'stages'             => TreatmentVisit::allStagesFromDb(),
            'lab_work_categories'=> LabCase::WORK_CATEGORIES,
            'lab_treatments'     => $labTreatments,
            'implant_fixtures'    => $implantFixtures,
            'implant_accessories' => $implantAccessories,
        ], '');
    }

    // ── Branch-scoped lookups (enveloped 404 across branches) ──────────────────

    private function findPatient(Request $request, $id): Patient
    {
        $patient = Patient::where('branch_id', $request->user()->branch_id)
            ->whereKey($id)
            ->first();

        if (! $patient) {
            $this->notFound('Patient not found.');
        }

        return $patient;
    }

    private function findVisit(Request $request, $id): TreatmentVisit
    {
        $visit = TreatmentVisit::with('patient:id,branch_id,name')
            ->whereKey($id)
            ->first();

        if (! $visit || ! $visit->patient ||
            (int) $visit->patient->branch_id !== (int) $request->user()->branch_id) {
            $this->notFound('Treatment visit not found.');
        }

        return $visit;
    }

    private function notFound(string $message): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => [],
        ], 404));
    }
}
