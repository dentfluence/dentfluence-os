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
        // Phase 1 additions
        'consent_status',
        'photo_type',
        'tag_treatment_type',
        'marketing_status',
    ];

    protected $casts = [
        'searchable_tags'       => 'array',
        'is_marketing'          => 'boolean',
        'watermark_applied'     => 'boolean',
        'treatment_start_date'  => 'date',
        'treatment_end_date'    => 'date',
        'upload_date'           => 'datetime',
    ];

    // ── Enum option lists (used by views / validation) ─────────

    public static array $consentOptions = [
        'not_given' => 'Not Given',
        'given'     => 'Given',
        'pending'   => 'Pending',
    ];

    public static array $photoTypeOptions = [
        'before'       => 'Before',
        'after'        => 'After',
        'before_after' => 'Before & After',
        'procedure'    => 'Procedure',
        'team'         => 'Team',
        'clinic'       => 'Clinic',
        'testimonial'  => 'Testimonial',
    ];

    public static array $treatmentTypeOptions = [
        'implant'        => 'Implant',
        'aligner'        => 'Aligner',
        'whitening'      => 'Whitening',
        'rct'            => 'Root Canal',
        'crown'          => 'Crown',
        'smile_makeover' => 'Smile Makeover',
        'braces'         => 'Braces',
        'extraction'     => 'Extraction',
        'veneer'         => 'Veneer',
        'other'          => 'Other',
    ];

    public static array $marketingStatusOptions = [
        'pending'  => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    // ── Helpers ───────────────────────────────────────────────

    /**
     * A photo is "fully tagged" when all 3 tagging fields are present.
     * Only fully-tagged + consent=given photos appear in the Marketing Library.
     */
    public function isFullyTagged(): bool
    {
        return filled($this->consent_status)
            && filled($this->photo_type)
            && filled($this->tag_treatment_type);
    }

    /**
     * Ready for the Marketing Library grid:
     * consent given, all tags set, explicitly approved.
     */
    public function isMarketingReady(): bool
    {
        return $this->consent_status === 'given'
            && $this->isFullyTagged()
            && $this->marketing_status === 'approved';
    }

    // ── Relationships ──────────────────────────────────────────

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }


    // ── Accessors ─────────────────────────────────────────────

    public function getDisplayUrlAttribute(): ?string
    {
        $path = $this->watermarked_path ?? $this->original_path;
        return $path ? Storage::url($path) : null;
    }

    public function getOriginalUrlAttribute(): ?string
    {
        return $this->original_path ? Storage::url($this->original_path) : null;
    }

    public function getMediaIconAttribute(): string
    {
        return match($this->media_type) {
            'photo'  => 'photo',
            'xray'   => 'xray',
            'video'  => 'video',
            'pdf'    => 'pdf',
            default  => 'other',
        };
    }

    public function getStageLabelAttribute(): string
    {
        return match($this->treatment_stage) {
            'before_treatment' => 'Before Treatment',
            'during_treatment' => 'During Treatment',
            'after_treatment'  => 'After Treatment',
            'follow_up'        => 'Follow-up',
            default            => 'General',
        };
    }

    public function getConsentLabelAttribute(): string
    {
        return self::$consentOptions[$this->consent_status] ?? ucfirst($this->consent_status ?? '');
    }

    public function getPhotoTypeLabelAttribute(): string
    {
        return self::$photoTypeOptions[$this->photo_type] ?? ucfirst($this->photo_type ?? '');
    }

    public function getTreatmentTypeLabelAttribute(): string
    {
        return self::$treatmentTypeOptions[$this->tag_treatment_type] ?? ucfirst($this->tag_treatment_type ?? '');
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

    /** @deprecated Use scopeMarketingReady() */
    public function scopeMarketing($query)
    {
        return $query->where('is_marketing', true);
    }

    public function scopeByStage($query, string $stage)
    {
        return $query->where('treatment_stage', $stage);
    }

    /** consent=given + fully tagged + approved */
    public function scopeMarketingReady($query)
    {
        return $query
            ->where('consent_status', 'given')
            ->whereNotNull('photo_type')
            ->whereNotNull('tag_treatment_type')
            ->where('marketing_status', 'approved');
    }

    /** Missing consent or any tag */
    public function scopePendingTags($query)
    {
        return $query->where(function ($q) {
            $q->where('consent_status', 'pending')
              ->orWhereNull('photo_type')
              ->orWhereNull('tag_treatment_type');
        });
    }

    public function scopeByTreatmentType($query, string $type)
    {
        return $query->where('tag_treatment_type', $type);
    }

    public function scopeByPhotoType($query, string $type)
    {
        return $query->where('photo_type', $type);
    }
}
