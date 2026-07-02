<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ConsultationCohaReport
 *
 * Stores the full structured data for a Comprehensive Oral Health Assessment.
 * COHA is a patient-awareness report — not a treatment plan.
 * It tells the patient: "Here is the state of your mouth. Here is what needs attention."
 *
 * The 9 JSON section columns mirror the COHA PDF sections.
 * pdf_path is populated after PDF generation in P2C7.
 *
 * Relationship chain:
 *   Consultation (consultation_type = 'coha')
 *     └─ ConsultationCohaReport
 *          └─ (generates) PDF at pdf_path
 */
class ConsultationCohaReport extends Model
{
    use HasFactory;

    protected $table = 'consultation_coha_reports';

    protected $fillable = [
        'consultation_id',
        'patient_id',
        'doctor_id',
        // Section JSON columns
        'extraoral',
        'soft_tissue',
        'tooth_assessment',
        'ortho_findings',
        'perio_findings',
        'esthetic_findings',
        'risk_assessment',
        'monitoring_teeth',
        'treatment_awareness',
        // Narrative
        'doctor_notes',
        'report_date',
        'pdf_path',
    ];

    protected $casts = [
        'extraoral'           => 'array',
        'soft_tissue'         => 'array',
        'tooth_assessment'    => 'array',
        'ortho_findings'      => 'array',
        'perio_findings'      => 'array',
        'esthetic_findings'   => 'array',
        'risk_assessment'     => 'array',
        'monitoring_teeth'    => 'array',
        'treatment_awareness' => 'array',
        'report_date'         => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Has this COHA report been converted to a PDF yet?
     */
    public function hasPdf(): bool
    {
        return !empty($this->pdf_path);
    }

    /**
     * Overall risk level across all dimensions.
     * Returns 'high' if any dimension is high, 'medium' if any is medium, else 'low'.
     */
    public function overallRisk(): string
    {
        $risks = array_values($this->risk_assessment ?? []);
        if (in_array('high', $risks)) return 'high';
        if (in_array('medium', $risks)) return 'medium';
        return 'low';
    }

    /**
     * Count how many teeth are flagged in tooth_assessment as needing attention
     * (i.e. not 'sound' or 'missing' or null).
     */
    public function teethNeedingAttention(): int
    {
        $healthy = ['sound', 'missing', 'implant', 'sound_crowned', null, ''];
        return count(array_filter(
            $this->tooth_assessment ?? [],
            fn($status) => !in_array($status, $healthy)
        ));
    }
}
