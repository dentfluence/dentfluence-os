<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Consultation extends Model
{
    use HasFactory, SoftDeletes;

    // ──────────────────────────────────────────────────────────────────────────
    // ↓↓↓  MERGE YOUR EXISTING $fillable / $casts / scopes / accessors BELOW ↓↓↓
    // ──────────────────────────────────────────────────────────────────────────

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'branch_id',
        'status',
        'consultation_date',
        'visit_type',
        'chief_complaint',
        'complaint_duration',
        'severity',
        'tooth_area',
        'location',
        'complaint_notes',
        'photographs',
        'scan_date',
        'scan_files',
        'investigations',
        'investigation_details',
        'clinical_data',
        'chart_data',
        'radio_data',
        'dbm_checklist',
        'dbm_score',
        'dbm_tooth_shade',
        'dbm_whitening',
        'dbm_tooth_monitored',
        'prescriptions',
        'instructions',
        'primary_diagnosis',
        'secondary_diagnosis',
        'risk_assessment',
        'diagnosis_notes',
        'tx_emergency',
        'tx_protective',
        'tx_transformative',
        'tx_teeth',
        'treatment_plan_best',
        'treatment_plan_best_total',
        'treatment_plan_acceptable',
        'treatment_plan_acc_total',
        'aocp_best',
        'aocp_best_plan',
        'aocp_acceptable',
        'aocp_acceptable_plan',
        'finishing_notes',
        'next_visit_type',
        'next_visit_date',
        'recall_interval',
        'recall_custom',
        'responsible_user_id',
        'attachments',
        // Brain / AI-first fields
        'raw_note',
        'tooth_numbers',
        'treatment_done',
        'treatment_plan_note',
        'follow_up_note',
        'follow_up_date',
        'risks_discussed',
        'treatment_acceptance',
        'prescription_notes',
        'examination_notes',
    ];
    protected $casts = [
        'photographs'        => 'array',
        'scan_files'         => 'array',
        'investigations'     => 'array',
        'investigation_details' => 'array',
        'clinical_data'      => 'array',
        'chart_data'         => 'array',
        'radio_data'         => 'array',
        'dbm_checklist'      => 'array',
        'prescriptions'      => 'array',
        'instructions'       => 'array',
        'tx_emergency'       => 'array',
        'tx_protective'      => 'array',
        'tx_transformative'  => 'array',
        'tx_teeth'           => 'array',
        'treatment_plan_best'       => 'array',
        'treatment_plan_acceptable' => 'array',
        'aocp_best'          => 'boolean',
        'aocp_acceptable'    => 'boolean',
        'tooth_numbers'      => 'array',
        'follow_up_date'     => 'date',
        'consultation_date'  => 'datetime',
        'next_visit_date'    => 'date',
        'attachments'        => 'array',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // Relationships — child models
    // ──────────────────────────────────────────────────────────────────────────

    public function photographs(): HasMany
    {
        return $this->hasMany(ConsultationPhotograph::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(ConsultationScan::class);
    }

    public function clinicalFindings(): HasMany
    {
        return $this->hasMany(ClinicalFinding::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    public function treatmentPlans(): HasMany
    {
        return $this->hasMany(TreatmentPlan::class);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ↑↑↑  Paste any remaining existing methods / scopes / accessors below  ↑↑↑
    // ──────────────────────────────────────────────────────────────────────────
}
