<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentVisit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'consultation_id',
        'treatment_plan_id',
        'doctor_id',
        'visit_date',
        'visit_type',
        'status',
        'procedure',
        'treatment_name',
        'current_stage',
        'completed_stages',
        'tooth_number',
        'notes',
        'chief_complaint',
        'cost',
        'amount_paid',
        'payment_mode',
        'payment_reference',
        'next_visit_date',
        'next_visit_type',

        // RCT
        'rct_num_canals',
        'rct_canal_lengths',
        'rct_file_type',
        'rct_irrigant',
        'rct_obturation_method',

        // Implant
        'impl_brand',
        'impl_size',
        'impl_torque',
        'impl_graft_used',
        'impl_graft_brand',
        'impl_membrane',
        'impl_healing_collar',

        // Filling
        'fill_material',
        'fill_shade',

        // Scaling
        'scale_quadrants',
        'scale_method',

        // Extraction
        'ext_type',
        'ext_socket',
        'ext_suture',

        // Crown prep
        'crown_type',
        'crown_shade',
        'crown_impression',
        'crown_temp_placed',

        // Prescription
        'prescription_drugs',
        'prescription_instructions',
        'prescription_custom_notes',
    ];

    protected $casts = [
        'visit_date'                  => 'date',
        'next_visit_date'             => 'date',
        'cost'                        => 'decimal:2',
        'amount_paid'                 => 'decimal:2',
        'completed_stages'            => 'array',
        'rct_canal_lengths'           => 'array',
        'prescription_drugs'          => 'array',
        'prescription_instructions'   => 'array',
        'ext_suture'                  => 'boolean',
        'crown_impression'            => 'boolean',
    ];

    // ── Treatment stage definitions ────────────────────────────────────────

    public static array $treatmentStages = [
        'RCT' => [
            'access_opening'    => 'Access Opening',
            'bmp'               => 'BMP (Biomechanical Prep)',
            'obturation'        => 'Obturation',
            'post_core'         => 'Post & Core',
            'restoration'       => 'Final Restoration',
        ],
        'Implant' => [
            'extraction'        => 'Extraction / Site Prep',
            'implant_placement' => 'Implant Placement',
            'healing'           => 'Healing / Osseointegration',
            'abutment'          => 'Abutment Placement',
            'final_crown'       => 'Final Crown / Prosthesis',
        ],
        'Crown Prep' => [
            'tooth_prep'        => 'Tooth Preparation',
            'impression'        => 'Impression',
            'temp_crown'        => 'Temporary Crown',
            'try_in'            => 'Try-in',
            'cementation'       => 'Final Cementation',
        ],
        'Filling' => [
            'caries_removal'    => 'Caries Removal',
            'base_liner'        => 'Base / Liner',
            'restoration'       => 'Restoration',
            'finishing'         => 'Finishing & Polishing',
        ],
        'Scaling' => [
            'scaling'           => 'Scaling',
            'root_planing'      => 'Root Planing',
            'polishing'         => 'Polishing',
            'review'            => 'Review',
        ],
        'Extraction' => [
            'extraction'        => 'Extraction',
            'socket_review'     => 'Socket Review',
            'suture_removal'    => 'Suture Removal',
        ],
    ];

    // ── Relations ──────────────────────────────────────────────────────────

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    // ── Accessors ──────────────────────────────────────────────────────────

    public function getBalanceDueAttribute(): float
    {
        return max(0, (float) $this->cost - (float) $this->amount_paid);
    }

    public function getIsFullyPaidAttribute(): bool
    {
        return $this->balance_due <= 0;
    }
}
