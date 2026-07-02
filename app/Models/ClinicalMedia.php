<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ClinicalMedia extends Model
{
    protected $table = 'clinical_media';

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'consultation_id',
        'visit_id',
        'treatment_name',
        'tooth_no',
        'treatment_stage',
        'media_type',
        'original_path',
        'watermarked_path',
        'disk',
        'tags',           // DB column
        'searchable_tags', // added via migration
        'original_filename',
        'file_size',
        'mime_type',
        'notes',
        'media_date',     // original DB column
        'visit_date',     // added via migration
        'upload_date',    // added via migration
    ];

    protected $casts = [
        'tags'           => 'array',
        'searchable_tags' => 'array',
        'visit_date'     => 'date',
        'media_date'     => 'date',
        'upload_date'    => 'date',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    // ── URL Accessors ──────────────────────────────────────────────────────────

    // Security (Phase A): URLs now point at the authenticated, branch-checked
    // SecureMediaController route instead of a public /storage URL. Add ?dl=1
    // for an audited download.
    public function getOriginalUrlAttribute(): string
    {
        return route('secure.media.legacy', $this->getKey());
    }

    public function getWatermarkedUrlAttribute(): ?string
    {
        if (!$this->watermarked_path) return null;
        return route('secure.media.legacy', [$this->getKey(), 'v' => 'wm']);
    }

    /** Returns watermarked URL if available, otherwise original */
    public function getDisplayUrlAttribute(): string
    {
        return $this->watermarked_url ?? $this->original_url;
    }

    public function getThumbnailUrlAttribute(): string
    {
        // For images, use display url; for non-images use a placeholder
        if ($this->isImage()) return $this->display_url;
        return asset('images/cms/file-placeholder.svg');
    }

    // ── Stage Helpers ──────────────────────────────────────────────────────────

    public function getStageLabelAttribute(): string
    {
        return config('cms.stage_labels.' . $this->treatment_stage, $this->treatment_stage ?? 'Unknown');
    }

    public function getStageColorAttribute(): string
    {
        return [
            'before'   => '#2563eb',
            'during'   => '#d97706',
            'after'    => '#16a34a',
            'followup' => '#7c3aed',
        ][$this->treatment_stage] ?? '#6b7280';
    }

    // ── Media Type Helpers ─────────────────────────────────────────────────────

    public function isImage(): bool
    {
        return in_array($this->media_type, ['photo', 'xray', 'opg', 'cbct', 'scan']);
    }

    public function isVideo(): bool
    {
        return $this->media_type === 'video';
    }

    public function isPdf(): bool
    {
        return $this->media_type === 'pdf';
    }

    public function getMediaTypeLabelAttribute(): string
    {
        return config('cms.media_type_labels.' . $this->media_type, ucfirst($this->media_type ?? 'File'));
    }

    public function getMediaTypeIconAttribute(): string
    {
        return [
            'photo'  => '📷',
            'xray'   => '🦷',
            'opg'    => '🦷',
            'cbct'   => '🔬',
            'scan'   => '📡',
            'video'  => '▶',
            'pdf'    => '📄',
        ][$this->media_type] ?? '📎';
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeForPatient($q, int $patientId)
    {
        return $q->where('patient_id', $patientId);
    }

    public function scopeForTooth($q, string $tooth)
    {
        return $q->where('tooth_no', $tooth);
    }

    public function scopeForTreatment($q, string $treatment)
    {
        return $q->where('treatment_name', 'like', "%{$treatment}%");
    }

    public function scopeForStage($q, string $stage)
    {
        return $q->where('treatment_stage', $stage);
    }

    public function scopeForDoctor($q, int $doctorId)
    {
        return $q->where('doctor_id', $doctorId);
    }

    public function scopeForType($q, string $type)
    {
        return $q->where('media_type', $type);
    }
}
