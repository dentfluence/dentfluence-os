<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class CmsMedia extends Model
{
    use SoftDeletes;

    protected $table = 'cms_media';

    protected $fillable = [
        'patient_id', 'consultation_id', 'visit_id', 'doctor_id',
        'treatment_name', 'tooth_no', 'treatment_stage', 'treatment_status',
        'media_type', 'original_filename', 'original_path', 'watermarked_path',
        'file_size', 'mime_type', 'searchable_tags',
        'is_marketing', 'watermark_applied',
        'treatment_start_date', 'treatment_end_date', 'upload_date',
    ];

    protected $casts = [
        'searchable_tags'       => 'array',
        'is_marketing'          => 'boolean',
        'watermark_applied'     => 'boolean',
        'treatment_start_date'  => 'date',
        'treatment_end_date'    => 'date',
        'upload_date'           => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    // ── Accessors ──────────────────────────────────────────────

    // Returns watermarked path if available, else original
    public function getDisplayUrlAttribute(): ?string
    {
        $path = $this->watermarked_path ?? $this->original_path;
        return $path ? Storage::url($path) : null;
    }

    public function getOriginalUrlAttribute(): ?string
    {
        return $this->original_path ? Storage::url($this->original_path) : null;
    }

    // Media type icon helper for blade
    public function getMediaIconAttribute(): string
    {
        return match($this->media_type) {
            'photo'  => '📷',
            'xray'   => '🦷',
            'opg'    => '🦷',
            'cbct'   => '🔬',
            'scan'   => '📡',
            'video'  => '🎥',
            'pdf'    => '📄',
            default  => '📎',
        };
    }

    // Stage label for UI
    public function getStageLabelAttribute(): string
    {
        return match($this->treatment_stage) {
            'before_treatment'  => 'Before Treatment',
            'during_treatment'  => 'During Treatment',
            'after_treatment'   => 'After Treatment',
            'follow_up'         => 'Follow-up',
            default             => 'General',
        };
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeForTreatment($query, string $treatment)
    {
        return $query->where('treatment_name', 'like', "%{$treatment}%");
    }

    public function scopeForTooth($query, string $tooth)
    {
        return $query->where('tooth_no', 'like', "%{$tooth}%");
    }

    public function scopeMarketing($query)
    {
        return $query->where('is_marketing', true);
    }

    public function scopeByStage($query, string $stage)
    {
        return $query->where('treatment_stage', $stage);
    }
}
