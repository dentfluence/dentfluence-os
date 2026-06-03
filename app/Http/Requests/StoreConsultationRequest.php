<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRoutine = $this->input('visit_type') === 'routine';

        return [
            // Core
            'patient_id'                  => ['nullable', 'exists:patients,id'],
            'doctor_id'                   => ['nullable', 'exists:users,id'],
            'branch_id'                   => ['nullable', 'integer'],
            'status'                      => ['nullable', 'in:draft,completed'],
            'consultation_date'           => ['nullable', 'date'],

            // Section 1 — Chief Complaint
            'chief_complaint'             => [$isRoutine ? 'required' : 'nullable', 'string'],
            'complaint_duration'          => ['nullable', 'string'],
            'severity'                    => ['nullable', 'string'],
            'tooth_area'                  => ['nullable', 'string'],
            'location'                    => ['nullable', 'string'],
            'complaint_notes'             => ['nullable', 'string'],

            // Visit type
            'visit_type'                  => ['nullable', 'in:emergency,routine,followup'],

            // Photographs
            'photographs'                 => ['nullable', 'array'],

            // Scans
            'scan_date'                   => ['nullable', 'date'],
            'scan_files'                  => ['nullable', 'array'],

            // Investigations
            'investigations'              => ['nullable', 'array'],
            'investigation_details'       => ['nullable', 'array'],

            // Clinical
            'clinical_data'               => ['nullable', 'string'],
            'chart_data'                  => ['nullable', 'string'],

            // Radiographic
            'radio_data'                  => ['nullable', 'string'],

            // DBM
            'dbm_checklist'               => ['nullable', 'string'],
            'dbm_score'                   => ['nullable', 'integer', 'min:0', 'max:100'],
            'dbm_tooth_shade'             => ['nullable', 'string'],
            'dbm_whitening'               => ['nullable', 'string'],
            'dbm_tooth_monitored'         => ['nullable', 'string'],

            // Prescriptions & Instructions
            'prescriptions'               => ['nullable', 'array'],
            'instructions'                => ['nullable', 'array'],

            // Diagnosis
            'primary_diagnosis'           => ['nullable', 'string'],
            'secondary_diagnosis'         => ['nullable', 'string'],
            'risk_assessment'             => ['nullable', 'string'],
            'diagnosis_notes'             => ['nullable', 'string'],

            // Treatment Options
            'tx_emergency'                => ['nullable', 'string'],
            'tx_protective'               => ['nullable', 'string'],
            'tx_transformative'           => ['nullable', 'string'],
            'tx_teeth'                    => ['nullable', 'array'],

            // Treatment Plans
            'treatment_plan_best'         => ['nullable', 'array'],
            'treatment_plan_best_total'   => ['nullable', 'numeric'],
            'treatment_plan_acceptable'   => ['nullable', 'array'],
            'treatment_plan_acc_total'    => ['nullable', 'numeric'],

            // AOCP
            'aocp_best'                   => ['nullable', 'boolean'],
            'aocp_best_plan'              => ['nullable', 'string'],
            'aocp_acceptable'             => ['nullable', 'boolean'],
            'aocp_acceptable_plan'        => ['nullable', 'string'],

            // ── Brain / AI-first fields ──────────────────────────────────────
            'raw_note'                    => ['nullable', 'string'],
            'tooth_numbers'               => ['nullable', 'array'],
            'tooth_numbers.*'             => ['string'],
            'treatment_done'              => ['nullable', 'string'],
            'treatment_plan_note'         => ['nullable', 'string'],
            'follow_up_note'              => ['nullable', 'string'],
            'follow_up_date'              => ['nullable', 'date'],
            'follow_up_days'              => ['nullable', 'integer'],
            'risks_discussed'             => ['nullable', 'string'],
            'treatment_acceptance'        => ['nullable', 'in:accepted,pending,refused,deferred'],
            'prescription_notes'          => ['nullable', 'string'],
            'examination_notes'           => ['nullable', 'string'],

            // Finishing
            'finishing_notes'             => ['nullable', 'string'],
            'next_visit_type'             => ['nullable', 'string'],
            'next_visit_date'             => ['nullable', 'date'],
            'recall_interval'             => ['nullable', 'string'],
            'recall_custom'               => ['nullable', 'string'],
            'responsible_user_id'         => ['nullable', 'exists:users,id'],
            'attachments'                 => ['nullable', 'array'],
        ];
    }
}
