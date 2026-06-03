<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\TreatmentVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TreatmentVisitController extends Controller
{
    public function store(Request $request, Patient $patient): JsonResponse
    {
        $data  = $this->validated($request);
        $visit = $patient->treatmentVisits()->create($data);
        $visit->load('doctor');

        return response()->json([
            'success' => true,
            'visit'   => $this->format($visit),
        ]);
    }

    public function update(Request $request, TreatmentVisit $visit): JsonResponse
    {
        $data = $this->validated($request);
        $visit->update($data);
        $visit->load('doctor');

        return response()->json([
            'success' => true,
            'visit'   => $this->format($visit),
        ]);
    }

    public function destroy(TreatmentVisit $visit): JsonResponse
    {
        $visit->delete();

        return response()->json(['success' => true]);
    }

    // ── Print visit ───────────────────────────────────────────────────────────
    public function print(TreatmentVisit $visit)
    {
        $visit->load(['patient', 'doctor']);
        $print  = \App\Models\AppSetting::group('print');
        $clinic = \App\Models\AppSetting::group('clinic');
        return view('visits.print', compact('visit', 'print', 'clinic'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validated(Request $request): array
    {
        return $request->validate([
            'visit_date'           => ['required', 'date'],
            'visit_type'           => ['required', Rule::in(['treatment','followup','emergency','recall'])],
            'status'               => ['required', Rule::in(['started','ongoing','completed','cancelled','no_show'])],
            'doctor_id'            => ['nullable', 'exists:users,id'],
            'consultation_id'      => ['nullable', 'exists:consultations,id'],
            'treatment_plan_id'    => ['nullable', 'exists:treatment_plans,id'],  // links visit to plan
            'treatment_name'       => ['nullable', 'string', 'max:100'],
            'current_stage'        => ['nullable', 'string', 'max:100'],
            'completed_stages'     => ['nullable', 'array'],
            'tooth_number'         => ['nullable', 'string', 'max:100'],
            'notes'                => ['nullable', 'string'],
            'chief_complaint'      => ['nullable', 'string', 'max:500'],
            'next_visit_date'      => ['nullable', 'date'],
            'next_visit_type'      => ['nullable', 'string', 'max:100'],
            // RCT
            'rct_num_canals'         => ['nullable', 'integer', 'min:1', 'max:5'],
            'rct_canal_lengths'      => ['nullable', 'array'],
            'rct_file_type'          => ['nullable', 'string', 'max:100'],
            'rct_irrigant'           => ['nullable', 'string', 'max:100'],
            'rct_obturation_method'  => ['nullable', 'string', 'max:100'],
            // Implant
            'impl_brand'             => ['nullable', 'string', 'max:100'],
            'impl_size'              => ['nullable', 'string', 'max:50'],
            'impl_torque'            => ['nullable', 'string', 'max:50'],
            'impl_graft_used'        => ['nullable', 'string', 'max:100'],
            'impl_graft_brand'       => ['nullable', 'string', 'max:100'],
            'impl_membrane'          => ['nullable', 'string', 'max:100'],
            'impl_healing_collar'    => ['nullable', 'string', 'max:100'],
            // Filling
            'fill_material'          => ['nullable', 'string', 'max:100'],
            'fill_shade'             => ['nullable', 'string', 'max:50'],
            // Scaling
            'scale_quadrants'        => ['nullable', 'string', 'max:100'],
            'scale_method'           => ['nullable', 'string', 'max:50'],
            // Extraction
            'ext_type'               => ['nullable', 'string', 'max:50'],
            'ext_socket'             => ['nullable', 'string', 'max:100'],
            'ext_suture'             => ['nullable', 'boolean'],
            // Crown prep
            'crown_type'             => ['nullable', 'string', 'max:50'],
            'crown_shade'            => ['nullable', 'string', 'max:50'],
            'crown_impression'       => ['nullable', 'boolean'],
            'crown_temp_placed'      => ['nullable', 'string', 'max:100'],
            // Prescription
            'prescription_drugs'         => ['nullable', 'array'],
            'prescription_instructions'  => ['nullable', 'array'],
            'prescription_custom_notes'  => ['nullable', 'string'],
        ]);
        // NOTE: cost, amount_paid, payment_mode, payment_reference intentionally removed.
        // Billing is managed by front desk via the Treatment Plan tab.
    }

    private function format(TreatmentVisit $v): array
    {
        return [
            'id'                  => $v->id,
            'visit_date'          => $v->visit_date->format('Y-m-d'),
            'visit_type'          => $v->visit_type,
            'status'              => $v->status,
            'doctor_id'           => $v->doctor_id,
            'doctor_name'         => $v->doctor?->name,
            'treatment_plan_id'   => $v->treatment_plan_id,
            'treatment_name'      => $v->treatment_name,
            'current_stage'       => $v->current_stage,
            'completed_stages'    => $v->completed_stages ?? [],
            'tooth_number'        => $v->tooth_number,
            'notes'               => $v->notes,
            'chief_complaint'     => $v->chief_complaint,
            'next_visit_date'     => $v->next_visit_date?->format('Y-m-d'),
            'next_visit_type'     => $v->next_visit_type,
            // RCT
            'rct_num_canals'         => $v->rct_num_canals,
            'rct_canal_lengths'      => $v->rct_canal_lengths ?? [],
            'rct_file_type'          => $v->rct_file_type,
            'rct_irrigant'           => $v->rct_irrigant,
            'rct_obturation_method'  => $v->rct_obturation_method,
            // Implant
            'impl_brand'             => $v->impl_brand,
            'impl_size'              => $v->impl_size,
            'impl_torque'            => $v->impl_torque,
            'impl_graft_used'        => $v->impl_graft_used,
            'impl_graft_brand'       => $v->impl_graft_brand,
            'impl_membrane'          => $v->impl_membrane,
            'impl_healing_collar'    => $v->impl_healing_collar,
            // Filling
            'fill_material'          => $v->fill_material,
            'fill_shade'             => $v->fill_shade,
            // Scaling
            'scale_quadrants'        => $v->scale_quadrants,
            'scale_method'           => $v->scale_method,
            // Extraction
            'ext_type'               => $v->ext_type,
            'ext_socket'             => $v->ext_socket,
            'ext_suture'             => $v->ext_suture,
            // Crown
            'crown_type'             => $v->crown_type,
            'crown_shade'            => $v->crown_shade,
            'crown_impression'       => $v->crown_impression,
            'crown_temp_placed'      => $v->crown_temp_placed,
            // Prescription
            'prescription_drugs'         => $v->prescription_drugs ?? [],
            'prescription_instructions'  => $v->prescription_instructions ?? [],
            'prescription_custom_notes'  => $v->prescription_custom_notes,
            '_isNew' => false,
        ];
    }
}
