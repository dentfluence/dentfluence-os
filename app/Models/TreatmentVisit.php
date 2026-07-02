<?php

namespace App\Models;

use App\Models\ClinicalFile;
use App\Models\LabCase;
use App\Models\Treatment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreatmentVisit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'appointment_id',
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
        'recall_queued_at',   // recall-engine cooldown stamp

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

        // Vitals (all optional — recorded via the collapsible Vitals section)
        'bp_systolic',
        'bp_diastolic',
        'pulse_rate',
        'spo2',
        'temperature',
        'blood_sugar',
        'blood_sugar_type',
        'weight',
        'vitals_notes',
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
        // Vitals
        'temperature'                 => 'decimal:1',
        'weight'                      => 'decimal:2',
    ];

    // ── Treatment stage definitions (loaded from Treatment module) ────────────

    /**
     * Returns stages for all treatments as a keyed map:
     *   [ "Implant" => ["stage_key" => "Stage Label", ...], ... ]
     *
     * Stages are defined per-treatment in the Treatment module (Stages tab).
     * Result is cached in-request via a static property.
     */
    public static function allStagesFromDb(): array
    {
        static $cache = null;

        if ($cache === null) {
            $cache = [];
            Treatment::select('name', 'stages')
                ->whereNotNull('stages')
                ->get()
                ->each(function ($t) use (&$cache) {
                    $stages = $t->stages ?? [];
                    if (!empty($stages)) {
                        // Convert [{key,label}] array → {key: label} map for JS compatibility
                        $cache[$t->name] = collect($stages)->pluck('label', 'key')->all();
                    }
                });
        }

        return $cache;
    }

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

    public function visitItems()
    {
        return $this->hasMany(TreatmentVisitItem::class);
    }

    public function billingPrompts()
    {
        return $this->hasMany(BillingPrompt::class, 'trigger_id')
                    ->where('trigger_type', 'treatment_visit');
    }

    /** Visit items not yet invoiced. */
    public function unbilledItems()
    {
        return $this->visitItems()->where('billing_status', 'pending');
    }

    /** Lab cases created from this visit. */
    public function labCases(): HasMany
    {
        return $this->hasMany(LabCase::class, 'treatment_visit_id');
    }

    /** Clinical files captured during this visit (Phase 9). */
    public function clinicalFiles(): HasMany
    {
        return $this->hasMany(ClinicalFile::class, 'visit_id');
    }

    /** Voice notes recorded during this treatment visit (polymorphic). */
    public function voiceNotes(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(VoiceNote::class, 'noteable')->latest();
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
