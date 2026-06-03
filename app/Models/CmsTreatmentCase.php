<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsTreatmentCase extends Model
{
    protected $table = 'cms_treatment_cases';

    protected $fillable = [
        'patient_id', 'doctor_id', 'treatment_name', 'tooth_no',
        'tags', 'start_date', 'completion_date', 'last_followup_date',
        'status', 'media_count', 'notes',
    ];

    protected $casts = [
        'tags'              => 'array',
        'start_date'        => 'date',
        'completion_date'   => 'date',
        'last_followup_date'=> 'date',
    ];

    // ── Relations ──────────────────────────────────────────
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ClinicalMedia::class, 'patient_id', 'patient_id')
            ->where('treatment_name', $this->treatment_name);
    }

    // ── Scopes ───────────────────────────────────────────────
    public function scopeSearch($q, string $term)
    {
        return $q->where(function($query) use ($term) {
            $query->where('treatment_name', 'like', '%'.$term.'%')
                  ->orWhere('tooth_no', 'like', '%'.$term.'%')
                  ->orWhereHas('patient', fn($p) => $p->where('name', 'like', '%'.$term.'%'));
        });
    }

    public function scopeFilterPatient($q, ?int $patientId)
    {
        return $patientId ? $q->where('patient_id', $patientId) : $q;
    }

    public function scopeFilterTooth($q, ?string $tooth)
    {
        return $tooth ? $q->where('tooth_no', 'like', '%'.$tooth.'%') : $q;
    }

    public function scopeFilterTreatment($q, ?string $treatment)
    {
        return $treatment ? $q->where('treatment_name', $treatment) : $q;
    }

    public function scopeFilterDoctor($q, ?int $doctorId)
    {
        return $doctorId ? $q->where('doctor_id', $doctorId) : $q;
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'completed' => 'green',
            'ongoing'   => 'orange',
            'paused'    => 'gray',
            'cancelled' => 'red',
            default     => 'gray',
        };
    }
}
