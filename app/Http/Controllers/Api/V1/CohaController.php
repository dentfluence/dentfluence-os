<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Consultation;
use App\Models\ConsultationCohaReport;
use App\Models\Patient;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CohaController (API v1)
 * ------------------------
 * The mobile face of COHA (Comprehensive Oral Health Assessment) — a
 * dedicated workflow separate from the 4 standard consultation types
 * (mirrors web's App\Http\Controllers\ConsultationController::cohaStore /
 * cohaUpdate). Same 9 JSON-section shape, same field names, so a report
 * created on mobile reads identically on web and vice versa.
 *
 *   GET  /api/v1/coha/{consultation}          one COHA report (edit prefill)
 *   POST /api/v1/patients/{patient}/coha       create
 *   PUT  /api/v1/coha/{consultation}           update
 *
 * Like the web version, the 9 section fields are intentionally NOT validated
 * field-by-field — they are doctor-authored clinical findings stored as JSON,
 * and the web form itself applies no enum validation either (see
 * ConsultationController::parseSection()). We only validate the shape (array)
 * so a malformed request can't crash the JSON cast.
 */
class CohaController extends ApiController
{
    private const SECTIONS = [
        'extraoral', 'soft_tissue', 'tooth_assessment', 'ortho_findings',
        'perio_findings', 'esthetic_findings', 'risk_assessment', 'treatment_awareness',
    ];

    /** Create a COHA assessment for a patient. */
    public function store(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);
        $data = $this->validated($request);

        $consultation = Consultation::create([
            'patient_id'        => $p->id,
            'branch_id'         => $p->branch_id,
            'doctor_id'         => $data['doctor_id'] ?? $request->user()->id,
            'consultation_type' => 'coha',
            'visit_type'        => 'routine',
            'consultation_date' => $data['consultation_date'] ?? now(),
            'status'            => 'completed',
            'chief_complaint'   => 'Comprehensive Oral Health Assessment',
            'doctor_notes'      => $data['doctor_notes'] ?? null,
        ]);

        $report = ConsultationCohaReport::create([
            'consultation_id'     => $consultation->id,
            'patient_id'          => $p->id,
            'doctor_id'           => $data['doctor_id'] ?? $request->user()->id,
            'report_date'         => $data['consultation_date'] ?? now(),
            'extraoral'           => $data['extraoral'] ?? [],
            'soft_tissue'         => $data['soft_tissue'] ?? [],
            'tooth_assessment'    => $data['tooth_assessment'] ?? [],
            'ortho_findings'      => $data['ortho_findings'] ?? [],
            'perio_findings'      => $data['perio_findings'] ?? [],
            'esthetic_findings'   => $data['esthetic_findings'] ?? [],
            'risk_assessment'     => $data['risk_assessment'] ?? [],
            'monitoring_teeth'    => $data['monitoring_teeth'] ?? [],
            'treatment_awareness' => $data['treatment_awareness'] ?? [],
            'doctor_notes'        => $data['doctor_notes'] ?? null,
        ]);

        $consultation->update(['coha_report_id' => $report->id]);

        return $this->success($this->payload($consultation, $report), 'COHA assessment saved.', 201);
    }

    /** One COHA report — used to prefill the mobile edit screen. */
    public function show(Request $request, $consultation): JsonResponse
    {
        $c = $this->findCoha($request, $consultation);

        return $this->success($this->payload($c, $c->cohaReport), '');
    }

    /** Update an existing COHA assessment. */
    public function update(Request $request, $consultation): JsonResponse
    {
        $c = $this->findCoha($request, $consultation);
        $data = $this->validated($request);

        $c->update([
            'doctor_id'         => $data['doctor_id'] ?? $c->doctor_id,
            'consultation_date' => $data['consultation_date'] ?? $c->consultation_date,
            'doctor_notes'      => $data['doctor_notes'] ?? $c->doctor_notes,
        ]);

        $report = $c->cohaReport;
        if ($report) {
            $report->update([
                'report_date'         => $data['consultation_date'] ?? $report->report_date,
                'extraoral'           => $data['extraoral'] ?? $report->extraoral,
                'soft_tissue'         => $data['soft_tissue'] ?? $report->soft_tissue,
                'tooth_assessment'    => $data['tooth_assessment'] ?? $report->tooth_assessment,
                'ortho_findings'      => $data['ortho_findings'] ?? $report->ortho_findings,
                'perio_findings'      => $data['perio_findings'] ?? $report->perio_findings,
                'esthetic_findings'   => $data['esthetic_findings'] ?? $report->esthetic_findings,
                'risk_assessment'     => $data['risk_assessment'] ?? $report->risk_assessment,
                'monitoring_teeth'    => $data['monitoring_teeth'] ?? $report->monitoring_teeth,
                'treatment_awareness' => $data['treatment_awareness'] ?? $report->treatment_awareness,
                'doctor_notes'        => $data['doctor_notes'] ?? $report->doctor_notes,
            ]);
        }

        return $this->success($this->payload($c->fresh(), $report?->fresh()), 'COHA assessment updated.');
    }

    private function validated(Request $request): array
    {
        $rules = [
            'doctor_id'          => ['nullable', 'integer', 'exists:users,id'],
            'consultation_date'  => ['nullable', 'date'],
            'doctor_notes'       => ['nullable', 'string'],
            'monitoring_teeth'   => ['nullable', 'array'],
            'monitoring_teeth.*' => ['string', 'max:10'],
        ];
        foreach (self::SECTIONS as $s) {
            $rules[$s] = ['nullable', 'array'];
        }

        return $request->validate($rules);
    }

    private function payload(Consultation $c, ?ConsultationCohaReport $r): array
    {
        return [
            'id'                  => $c->id,
            'patient_id'          => $c->patient_id,
            'doctor_id'           => $c->doctor_id,
            'date'                => optional($c->consultation_date)->toDateString(),
            'status'              => $c->status,
            'type'                => $c->consultation_type,
            'doctor_notes'        => $r->doctor_notes ?? $c->doctor_notes,
            'extraoral'           => $r->extraoral ?? [],
            'soft_tissue'         => $r->soft_tissue ?? [],
            'tooth_assessment'    => $r->tooth_assessment ?? [],
            'ortho_findings'      => $r->ortho_findings ?? [],
            'perio_findings'      => $r->perio_findings ?? [],
            'esthetic_findings'   => $r->esthetic_findings ?? [],
            'risk_assessment'     => $r->risk_assessment ?? [],
            'monitoring_teeth'    => $r->monitoring_teeth ?? [],
            'treatment_awareness' => $r->treatment_awareness ?? [],
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

    private function findCoha(Request $request, $id): Consultation
    {
        $c = Consultation::with(['patient:id,branch_id', 'cohaReport'])
            ->where('consultation_type', 'coha')
            ->whereKey($id)
            ->first();

        if (! $c || ! $c->patient || (int) $c->patient->branch_id !== (int) $request->user()->branch_id) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'COHA report not found.',
                'errors'  => [],
            ], 404));
        }

        return $c;
    }
}
