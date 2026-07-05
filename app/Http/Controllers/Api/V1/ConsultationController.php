<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\AppSetting;
use App\Models\Consultation;
use App\Models\ConsultationSpecialtyModule;
use App\Models\Patient;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ConsultationController (API v1)
 * -------------------------------
 * Mirrors the web's four consultation workflows so mobile records are identical
 * to web ones. Each just creates a Consultation row (the web's heavy extras —
 * charting, photo uploads, specialty modules — are optional and simply omitted
 * on mobile; the record stays valid and editable on web).
 *
 *   POST /api/v1/patients/{patient}/consultations              (New)
 *   POST /api/v1/patients/{patient}/consultations/same-issue
 *   POST /api/v1/patients/{patient}/consultations/minor-visit
 *   POST /api/v1/patients/{patient}/consultations/emergency
 */
class ConsultationController extends ApiController
{
    public function storeNew(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);
        $data = $request->validate([
            'doctor_id'              => ['nullable', 'integer', 'exists:users,id'],
            'consultation_date'      => ['nullable', 'date'],
            'chief_complaint'        => ['required', 'string', 'max:1000'],
            'complaint_duration'     => ['nullable', 'string', 'max:100'],
            'severity'               => ['nullable', 'string', 'max:50'],
            'tooth_area'             => ['nullable', 'string', 'max:100'],
            'hopi_final'             => ['nullable', 'string'],
            'findings_summary_final' => ['nullable', 'string'],
            'provisional_diagnosis'  => ['nullable', 'string'],
            'diagnosis_notes'        => ['nullable', 'string'],
            'diagnosis_risk'         => ['nullable', 'string', 'max:50'],
            'finishing_notes'        => ['nullable', 'string'],
            // Tooth chart: { "16": "rct", "11": "crown", ... } — same column the
            // web reads (chart_data, cast to array on the model).
            'chart_data'             => ['nullable', 'array'],
            'investigations'         => ['nullable', 'array'],
            // e.g. { "_notes": "IOPA of 36 before RCT" } — mirrors the web's
            // investigations partial notes textarea.
            'investigation_details'  => ['nullable', 'array'],
            'accepted_specialties'   => ['nullable', 'array'],
            'specialty_modules'                 => ['nullable', 'array'],
            'specialty_modules.*.specialty_tag' => ['required_with:specialty_modules', 'string', 'max:50'],
            'specialty_modules.*.findings'      => ['nullable', 'array'],
        ]);

        // Doctor = the logged-in user (no picker on mobile).
        $data['doctor_id'] = $data['doctor_id'] ?? $request->user()->id;

        $modules = $data['specialty_modules'] ?? [];
        unset($data['specialty_modules']);

        $consultation = $this->make($p, $data, 'new', 'routine');

        foreach ($modules as $m) {
            if (empty($m['specialty_tag'])) continue;
            ConsultationSpecialtyModule::create([
                'consultation_id' => $consultation->id,
                'specialty_tag'   => $m['specialty_tag'],
                'findings'        => $m['findings'] ?? [],
                'accepted_at'     => now(),
            ]);
        }

        return $this->success($this->payload($consultation), 'Consultation saved.', 201);
    }

    public function storeSameIssue(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);
        $data = $request->validate([
            'doctor_id'                => ['nullable', 'integer', 'exists:users,id'],
            'consultation_date'        => ['nullable', 'date'],
            'previous_consultation_id' => ['nullable', 'integer', 'exists:consultations,id'],
            'update_notes'             => ['required', 'string'],
            'additional_findings'      => ['nullable', 'string'],
            'primary_diagnosis'        => ['nullable', 'string'],
            'diagnosis_notes'          => ['nullable', 'string'],
            'finishing_notes'          => ['nullable', 'string'],
        ]);

        $data['doctor_id'] = $data['doctor_id'] ?? $request->user()->id;

        return $this->create($p, $data, 'same_issue', 'routine');
    }

    public function storeMinorVisit(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);
        $data = $request->validate([
            'doctor_id'                   => ['nullable', 'integer', 'exists:users,id'],
            'consultation_date'           => ['nullable', 'date'],
            'related_to_clinic_treatment' => ['nullable', 'boolean'],
            'procedure_performed'         => ['required', 'string'],
            'chief_complaint'             => ['nullable', 'string'],
            'hopi_final'                  => ['nullable', 'string'],
            'primary_diagnosis'           => ['nullable', 'string'],
            // External / walk-in visits store exam findings in clinical_data[notes]
            // (same column the web form writes), so mobile records match web.
            'clinical_data'               => ['nullable', 'array'],
            'advice'                      => ['nullable', 'string'],
            'finishing_notes'             => ['nullable', 'string'],
        ]);
        $data['related_to_clinic_treatment'] = $data['related_to_clinic_treatment'] ?? false;
        $data['doctor_id'] = $data['doctor_id'] ?? $request->user()->id;

        return $this->create($p, $data, 'minor_visit', 'routine');
    }

    public function storeEmergency(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);
        $data = $request->validate([
            'doctor_id'                    => ['nullable', 'integer', 'exists:users,id'],
            'consultation_date'            => ['nullable', 'date'],
            'chief_complaint'              => ['required', 'string'],
            'hopi_final'                   => ['nullable', 'string'],
            'clinical_data'                => ['nullable', 'array'],
            'primary_diagnosis'            => ['nullable', 'string'],
            'emergency_treatment_rendered' => ['required', 'string'],
            'advice'                       => ['nullable', 'string'],
            'finishing_notes'              => ['nullable', 'string'],
        ]);

        $data['doctor_id'] = $data['doctor_id'] ?? $request->user()->id;

        return $this->create($p, $data, 'emergency', 'emergency');
    }

    /** Full consultation (Case Paper) for print / share. */
    public function show(Request $request, $consultation): JsonResponse
    {
        $c = Consultation::with([
                'patient:id,branch_id,name,patient_id,phone,gender,age_years,address,area,city',
                'doctor:id,name',
                'specialtyModules',
            ])
            ->whereKey($consultation)
            ->first();

        if (! $c || ! $c->patient ||
            (int) $c->patient->branch_id !== (int) $request->user()->branch_id) {
            return $this->error('Consultation not found.', [], 404);
        }

        $cs = AppSetting::where('group', 'clinic')->pluck('value', 'key');

        return $this->success([
            'clinic' => [
                'name'    => $cs->get('name') ?? $cs->get('clinic_name') ?? config('app.name'),
                'phone'   => $cs->get('phone') ?? $cs->get('contact'),
                'address' => $cs->get('address'),
            ],
            'patient' => [
                'name'       => $c->patient->name,
                'patient_id' => $c->patient->patient_id,
                'phone'      => $c->patient->phone,
                'gender'     => $c->patient->gender,
                'age'        => $c->patient->age_years,
                'address'    => trim(collect([
                    $c->patient->address, $c->patient->area, $c->patient->city,
                ])->filter()->join(', ')),
            ],
            'doctor'                 => $c->doctor?->name,
            'date'                   => $c->consultation_date,
            'type'                   => $c->consultation_type,
            'status'                 => $c->status,
            'chief_complaint'        => $c->chief_complaint,
            'complaint_duration'     => $c->complaint_duration,
            'severity'               => $c->severity,
            'tooth_area'             => $c->tooth_area,
            'hopi_final'             => $c->hopi_final,
            'findings_summary_final' => $c->findings_summary_final,
            'provisional_diagnosis'  => $c->provisional_diagnosis ?: $c->primary_diagnosis,
            'primary_diagnosis'      => $c->primary_diagnosis,
            'diagnosis_risk'         => $c->diagnosis_risk,
            'diagnosis_notes'        => $c->diagnosis_notes,
            'finishing_notes'        => $c->finishing_notes,
            // Workflow-specific raw fields (for edit prefill)
            'update_notes'                 => $c->update_notes,
            'additional_findings'          => $c->additional_findings,
            'related_to_clinic_treatment'  => $c->related_to_clinic_treatment,
            'procedure_performed'          => $c->procedure_performed,
            'advice'                       => $c->advice,
            'emergency_treatment_rendered' => $c->emergency_treatment_rendered,
            'clinical_data'          => $c->clinical_data,
            'investigations'         => $c->investigations,
            'investigation_details'  => $c->investigation_details,
            'chart_data'             => $c->chart_data,
            'specialty_modules'      => $c->specialtyModules->map(fn ($m) => [
                'tag'      => $m->specialty_tag,
                'findings' => $m->findings,
            ])->values(),
        ], '');
    }

    /** Update an existing consultation (any workflow). */
    public function update(Request $request, $consultation): JsonResponse
    {
        $c = Consultation::with('patient:id,branch_id')->whereKey($consultation)->first();
        if (! $c || ! $c->patient ||
            (int) $c->patient->branch_id !== (int) $request->user()->branch_id) {
            return $this->error('Consultation not found.', [], 404);
        }

        $data = $request->validate([
            'consultation_date'            => ['nullable', 'date'],
            'chief_complaint'              => ['nullable', 'string'],
            'complaint_duration'           => ['nullable', 'string', 'max:100'],
            'severity'                     => ['nullable', 'string', 'max:50'],
            'tooth_area'                   => ['nullable', 'string', 'max:100'],
            'hopi_final'                   => ['nullable', 'string'],
            'findings_summary_final'       => ['nullable', 'string'],
            'provisional_diagnosis'        => ['nullable', 'string'],
            'primary_diagnosis'            => ['nullable', 'string'],
            'diagnosis_notes'              => ['nullable', 'string'],
            'diagnosis_risk'               => ['nullable', 'string', 'max:50'],
            'finishing_notes'              => ['nullable', 'string'],
            'update_notes'                 => ['nullable', 'string'],
            'additional_findings'          => ['nullable', 'string'],
            'related_to_clinic_treatment'  => ['nullable', 'boolean'],
            'procedure_performed'          => ['nullable', 'string'],
            'advice'                       => ['nullable', 'string'],
            'emergency_treatment_rendered' => ['nullable', 'string'],
            'clinical_data'                => ['nullable', 'array'],
            'chart_data'                   => ['nullable', 'array'],
            'investigations'               => ['nullable', 'array'],
            'investigation_details'        => ['nullable', 'array'],
            'accepted_specialties'         => ['nullable', 'array'],
            'specialty_modules'                 => ['nullable', 'array'],
            'specialty_modules.*.specialty_tag' => ['required_with:specialty_modules', 'string', 'max:50'],
            'specialty_modules.*.findings'      => ['nullable', 'array'],
        ]);

        $modules = $data['specialty_modules'] ?? null;
        unset($data['specialty_modules']);

        if (! empty($data)) {
            $c->update($data);
        }

        if ($modules !== null) {
            $tags = collect($modules)->pluck('specialty_tag')->filter()->all();
            ConsultationSpecialtyModule::where('consultation_id', $c->id)
                ->whereNotIn('specialty_tag', $tags)
                ->delete();
            foreach ($modules as $m) {
                if (empty($m['specialty_tag'])) continue;
                ConsultationSpecialtyModule::updateOrCreate(
                    ['consultation_id' => $c->id, 'specialty_tag' => $m['specialty_tag']],
                    ['findings' => $m['findings'] ?? [], 'accepted_at' => now(), 'rejected_at' => null],
                );
            }
        }

        return $this->success($this->payload($c->fresh()), 'Consultation updated.');
    }

    /** Context for the Same Issue form: last main consultation + treatment plans. */
    public function sameIssueContext(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);

        $prev = $p->consultations()
            ->whereNotIn('consultation_type', ['coha', 'minor_visit', 'emergency'])
            ->latest('consultation_date')
            ->with('doctor:id,name')
            ->first();

        $plans = $p->treatmentPlans()
            ->latest()
            ->get()
            ->map(fn ($t) => [
                'id'         => $t->id,
                'name'       => $t->plan_name,
                'status'     => $t->status,
                'created_at' => $t->created_at,
            ])
            ->values();

        return $this->success([
            'previous' => $prev ? [
                'id'              => $prev->id,
                'date'            => $prev->consultation_date,
                'type'            => $prev->consultation_type,
                'doctor'          => $prev->doctor?->name,
                'chief_complaint' => $prev->chief_complaint,
            ] : null,
            'treatment_plans' => $plans,
        ], '');
    }

    private function create(Patient $p, array $data, string $type, string $visitType): JsonResponse
    {
        return $this->success(
            $this->payload($this->make($p, $data, $type, $visitType)),
            'Consultation saved.',
            201
        );
    }

    private function make(Patient $p, array $data, string $type, string $visitType): Consultation
    {
        return Consultation::create(array_merge($data, [
            'patient_id'        => $p->id,
            'branch_id'         => $p->branch_id,
            'consultation_type' => $type,
            'visit_type'        => $visitType,
            'status'            => 'completed',
            'consultation_date' => $data['consultation_date'] ?? now(),
        ]));
    }

    private function payload(Consultation $consultation): array
    {
        return [
            'id'              => $consultation->id,
            'date'            => $consultation->consultation_date,
            'status'          => $consultation->status,
            'type'            => $consultation->consultation_type,
            'chief_complaint' => $consultation->chief_complaint,
            'diagnosis'       => $consultation->provisional_diagnosis
                ?: $consultation->primary_diagnosis,
        ];
    }

    private function find(Request $request, $id): Patient
    {
        $patient = Patient::where('branch_id', $request->user()->branch_id)
            ->whereKey($id)
            ->first();

        if (! $patient) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Patient not found.',
                'errors'  => [],
            ], 404));
        }

        return $patient;
    }
}
