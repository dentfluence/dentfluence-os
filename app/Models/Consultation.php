<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Consultation extends Model
{
    use HasFactory, SoftDeletes, \App\Traits\BelongsToBranch, \App\Traits\Auditable;

    /** Tag audit-log entries for this model with the "consultations" module. */
    protected $auditModule = 'consultations';

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
        'consultation_type',
        'hopi_auto',
        'hopi_final',
        'findings_summary_auto',
        'findings_summary_final',
        'specialty_findings',
        'accepted_specialties',
        'provisional_diagnosis',
        'differential_diagnosis',
        'previous_consultation_id',
        'coha_report_id',
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
        'diagnosis_risk',      // P2C6 — Low/Moderate/High/Very High Risk
        'diagnosis_icd_code',  // P2C6 — optional ICD-10-CM code
        // P2C11c: legacy tx_* / treatment_plan_* / aocp_* columns removed from mass-assignment.
        // Columns still exist in DB for show.blade.php to display historical data.
        // Run migration_drop_consultation_legacy_tx_columns when ready to drop DB columns.
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
        // Typed consultation fields (refactor 2026-06-17)
        'update_notes',
        'additional_findings',
        'related_to_clinic_treatment',
        'procedure_performed',
        'advice',
        'emergency_treatment_rendered',
        'converted_to_consultation_id',
        'appointment_id',   // optional link for backdated entry
    ];
    protected $casts = [
        // Sensitive clinical JSON — encrypted at rest (Phase A, EncryptedArray).
        'specialty_findings'        => \App\Casts\EncryptedArray::class,
        'accepted_specialties'      => \App\Casts\EncryptedArray::class,
        'investigations'            => \App\Casts\EncryptedArray::class,
        'investigation_details'     => \App\Casts\EncryptedArray::class,
        'clinical_data'             => \App\Casts\EncryptedArray::class,
        'chart_data'                => \App\Casts\EncryptedArray::class,
        'radio_data'                => \App\Casts\EncryptedArray::class,
        'dbm_checklist'             => \App\Casts\EncryptedArray::class,
        'prescriptions'             => \App\Casts\EncryptedArray::class,
        'instructions'              => \App\Casts\EncryptedArray::class,
        'tx_emergency'              => \App\Casts\EncryptedArray::class,
        'tx_protective'             => \App\Casts\EncryptedArray::class,
        'tx_transformative'         => \App\Casts\EncryptedArray::class,
        'tx_teeth'                  => \App\Casts\EncryptedArray::class,
        'treatment_plan_best'       => \App\Casts\EncryptedArray::class,
        'treatment_plan_acceptable' => \App\Casts\EncryptedArray::class,
        // File-reference / non-narrative arrays stay plain.
        'photographs'             => 'array',
        'scan_files'         => 'array',
        'aocp_best'          => 'boolean',
        'aocp_acceptable'    => 'boolean',
        'tooth_numbers'      => 'array',
        'follow_up_date'     => 'date',
        'consultation_date'  => 'datetime',
        'next_visit_date'    => 'date',
        'attachments'        => 'array',

        // ── Encrypted free-text clinical notes at rest (Phase A) ─────────────
        // Sensitive narrative health data. Resilient casts (app/Casts/Encrypted)
        // encrypt on write and fall back to plaintext on read until the backfill
        // has run. All are plain text columns and none are queried in SQL
        // (verified) — JSON/structured fields (clinical_data, chart_data,
        // instructions, etc.) are intentionally left for a later pass.
        'hopi_auto'                   => \App\Casts\Encrypted::class,
        'hopi_final'                  => \App\Casts\Encrypted::class,
        'findings_summary_auto'       => \App\Casts\Encrypted::class,
        'findings_summary_final'      => \App\Casts\Encrypted::class,
        'complaint_notes'             => \App\Casts\Encrypted::class,
        'diagnosis_notes'             => \App\Casts\Encrypted::class,
        'primary_diagnosis'           => \App\Casts\Encrypted::class,
        'secondary_diagnosis'         => \App\Casts\Encrypted::class,
        'provisional_diagnosis'       => \App\Casts\Encrypted::class,
        'differential_diagnosis'      => \App\Casts\Encrypted::class,
        'advice'                      => \App\Casts\Encrypted::class,
        'examination_notes'           => \App\Casts\Encrypted::class,
        'prescription_notes'          => \App\Casts\Encrypted::class,
        'treatment_done'              => \App\Casts\Encrypted::class,
        'treatment_plan_note'         => \App\Casts\Encrypted::class,
        'follow_up_note'              => \App\Casts\Encrypted::class,
        'raw_note'                    => \App\Casts\Encrypted::class,
        'additional_findings'         => \App\Casts\Encrypted::class,
        'finishing_notes'             => \App\Casts\Encrypted::class,
        'risk_assessment'             => \App\Casts\Encrypted::class,
        'procedure_performed'         => \App\Casts\Encrypted::class,
        'emergency_treatment_rendered'=> \App\Casts\Encrypted::class,
    ];

    /**
     * Flat list of tooth numbers marked in chart_data, regardless of which
     * shape the row was saved in:
     *   - rows saved 2026-07-13 onward: array of {tooth, condition, custom, surfaces}
     *   - legacy rows: flat array of tooth numbers only (no condition ever saved)
     * Any code that just needs "which teeth were marked" (tooth timelines,
     * "previous treatment on this tooth" lookups, etc.) should call this
     * instead of reading chart_data directly.
     */
    public function chartToothNumbers(): array
    {
        return collect($this->chart_data ?? [])
            ->map(fn ($e) => is_array($e) ? ($e['tooth'] ?? null) : $e)
            ->filter(fn ($t) => $t !== null && $t !== '')
            ->map(fn ($t) => (int) $t)
            ->values()
            ->all();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Relationships — child models
    // ──────────────────────────────────────────────────────────────────────────

    public function appointment()
    {
        return $this->belongsTo(\App\Models\Appointment::class);
    }

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

    public function specialtyModules(): HasMany
    {
        return $this->hasMany(ConsultationSpecialtyModule::class);
    }

    public function cohaReport(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ConsultationCohaReport::class);
    }

    public function doctor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function responsible(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function patient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function previousConsultation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Consultation::class, 'previous_consultation_id');
    }

    /** Voice notes recorded during this consultation (polymorphic). */
    public function voiceNotes(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(VoiceNote::class, 'noteable')->latest();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Human-readable consultation type label. */
    public function typeLabel(): string
    {
        return match($this->consultation_type ?? $this->visit_type) {
            'new'        => 'New Consultation',
            'followup'   => 'Follow-Up',
            'same_issue' => 'Same Issue Follow-Up',
            'recall_6m'  => '6 Month Recall',
            'emergency'  => 'Emergency',
            'minor_visit'=> 'Minor Visit',
            'coha'       => 'COHA',
            default      => ucfirst($this->visit_type ?? 'Consultation'),
        };
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ↑↑↑  Paste any remaining existing methods / scopes / accessors below  ↑↑↑
    // ──────────────────────────────────────────────────────────────────────────
}
