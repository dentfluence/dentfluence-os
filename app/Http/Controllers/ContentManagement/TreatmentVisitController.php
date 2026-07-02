<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\TreatmentVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TreatmentVisitController extends Controller
{
    /**
     * POST /patients/{patient}/visits
     */
    public function store(Request $request, Patient $patient): JsonResponse
    {
        $data = $request->validate([
            'consultation_id'    => ['nullable', 'exists:consultations,id'],
            'doctor_id'          => ['nullable', 'exists:users,id'],
            'visit_date'         => ['required', 'date'],
            'visit_type'         => ['required', Rule::in(['treatment', 'followup', 'emergency', 'recall'])],
            'status'             => ['required', Rule::in(['scheduled','in_chair','completed','cancelled','no_show'])],
            'procedure'          => ['nullable', 'string', 'max:255'],
            'tooth_number'       => ['nullable', 'string', 'max:100'],
            'notes'              => ['nullable', 'string'],
            'chief_complaint'    => ['nullable', 'string', 'max:500'],
            'cost'               => ['nullable', 'numeric', 'min:0'],
            'amount_paid'        => ['nullable', 'numeric', 'min:0'],
            'payment_mode'       => ['nullable', Rule::in(['cash', 'upi', 'card', 'bank_transfer', 'insurance', 'pending'])],
            'payment_reference'  => ['nullable', 'string', 'max:255'],
            'next_visit_date'    => ['nullable', 'date', 'after_or_equal:visit_date'],
            'next_visit_type'    => ['nullable', 'string', 'max:100'],
        ]);

        $visit = $patient->treatmentVisits()->create($data);
        $visit->load('doctor');

        return response()->json([
            'success' => true,
            'visit'   => $this->formatVisit($visit),
            'totals'  => $this->totals($patient),
        ]);
    }

    /**
     * PUT /visits/{visit}
     */
    public function update(Request $request, TreatmentVisit $visit): JsonResponse
    {
        $data = $request->validate([
            'consultation_id'    => ['nullable', 'exists:consultations,id'],
            'doctor_id'          => ['nullable', 'exists:users,id'],
            'visit_date'         => ['required', 'date'],
            'visit_type'         => ['required', Rule::in(['treatment', 'followup', 'emergency', 'recall'])],
            'status'             => ['required', Rule::in(['scheduled', 'in_chair', 'completed', 'cancelled', 'no_show'])],
            'procedure'          => ['nullable', 'string', 'max:255'],
            'tooth_number'       => ['nullable', 'string', 'max:100'],
            'notes'              => ['nullable', 'string'],
            'chief_complaint'    => ['nullable', 'string', 'max:500'],
            'cost'               => ['nullable', 'numeric', 'min:0'],
            'amount_paid'        => ['nullable', 'numeric', 'min:0'],
            'payment_mode'       => ['nullable', Rule::in(['cash', 'upi', 'card', 'bank_transfer', 'insurance', 'pending'])],
            'payment_reference'  => ['nullable', 'string', 'max:255'],
            'next_visit_date'    => ['nullable', 'date'],
            'next_visit_type'    => ['nullable', 'string', 'max:100'],
        ]);

        $visit->update($data);
        $visit->load('doctor');

        return response()->json([
            'success' => true,
            'visit'   => $this->formatVisit($visit),
            'totals'  => $this->totals($visit->patient),
        ]);
    }

    /**
     * DELETE /visits/{visit}
     */
    public function destroy(TreatmentVisit $visit): JsonResponse
    {
        $patient = $visit->patient;
        $visit->delete();

        return response()->json([
            'success' => true,
            'totals'  => $this->totals($patient),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function formatVisit(TreatmentVisit $v): array
    {
        return [
            'id'               => $v->id,
            'visit_date'       => $v->visit_date->format('Y-m-d'),
            'visit_date_label' => $v->visit_date->format('d M Y'),
            'visit_type'       => $v->visit_type,
            'status'           => $v->status,
            'procedure'        => $v->procedure,
            'tooth_number'     => $v->tooth_number,
            'notes'            => $v->notes,
            'chief_complaint'  => $v->chief_complaint,
            'cost'             => (float) $v->cost,
            'amount_paid'      => (float) $v->amount_paid,
            'balance_due'      => $v->balance_due,
            'payment_mode'     => $v->payment_mode,
            'payment_reference'=> $v->payment_reference,
            'next_visit_date'  => $v->next_visit_date?->format('Y-m-d'),
            'next_visit_type'  => $v->next_visit_type,
            'doctor_name'      => $v->doctor?->name,
            'consultation_id'  => $v->consultation_id,
        ];
    }

    private function totals(Patient $patient): array
    {
        $visits = $patient->treatmentVisits()->whereNull('deleted_at');

        return [
            'total_visits'    => (clone $visits)->count(),
            'total_cost'      => (float) (clone $visits)->sum('cost'),
            'total_paid'      => (float) (clone $visits)->sum('amount_paid'),
            'total_balance'   => (float) (clone $visits)->selectRaw('SUM(cost - amount_paid) as bal')->value('bal'),
            'completed'       => (clone $visits)->where('status', 'completed')->count(),
            'scheduled'       => (clone $visits)->whereIn('status', ['scheduled', 'in_chair'])->count(),
        ];
    }
}
