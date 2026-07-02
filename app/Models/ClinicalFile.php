<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 7B — ClinicalFile Model
 *
 * The single source of truth for all clinical documentation.
 * Replaces clinical_media, cms_media, and patient_documents (Phase 8).
 *
 * Every file is anchored to a patient. Visit and treatment plan item are optional.
 */
class ClinicalFile extends Model
{
    use SoftDeletes;

    protected $table = 'clinical_files';

    // ── Fillable ───────────────────────────────────────────────────────────────

    protected $fillable = [
        // Anchors
        'patient_id',
        'visit_id',
        'treatment_plan_item_id',
        // Clinical context
        'procedure',
        'tooth_number',
        'stage',
        // Classification
        'file_type',
        'title',
        'notes',
        // Storage
        'disk',
        'path',
        'watermarked_path',
        // File metadata
        'original_filename',
        'mime_type',
        'file_size',
        'captured_at',
        'uploaded_by',
        // Source tracing
        'source_type',
        'source_id',
        'protocol_step_id',
        // Sync
        'sync_status',
        // Eligibility flags
        'is_marketing_eligible',
        'is_education_eligible',
        'is_teaching_eligible',
        'is_research_eligible',
        'is_case_library_eligible',
        // Consent & approval
        'consent_status',
        'marketing_status',
        // Optional metadata
        'content_rating',
        'tags',
    ];

    // ── Casts ──────────────────────────────────────────────────────────────────

    protected $casts = [
        'captured_at'              => 'datetime',
        'is_marketing_eligible'    => 'boolean',
        'is_education_eligible'    => 'boolean',
        'is_teaching_eligible'     => 'boolean',
        'is_research_eligible'     => 'boolean',
        'is_case_library_eligible' => 'boolean',
        'tags'                     => 'array',
        'file_size'                => 'integer',
        'content_rating'           => 'integer',
    ];

    // ── Enum Constants ─────────────────────────────────────────────────────────

    const STAGES = ['general', 'before', 'during', 'after', 'followup'];

    const FILE_TYPES = [
        'photo', 'video', 'xray', 'opg', 'cbct', 'stl',
        'intraoral_scan', 'pdf', 'consent', 'estimate',
        'invoice', 'lab_slip', 'other',
    ];

    const IMAGE_TYPES = ['photo', 'xray', 'opg', 'cbct', 'intraoral_scan'];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(TreatmentVisit::class, 'visit_id');
    }

    public function treatmentPlanItem(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlanItem::class, 'treatment_plan_item_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function protocolStep(): BelongsTo
    {
        return $this->belongsTo(DocumentationProtocolStep::class, 'protocol_step_id');
    }

    // ── URL Accessors ──────────────────────────────────────────────────────────

    /**
     * Original file URL.
     *
     * Security (Phase A): this no longer returns a public /storage URL. It points
     * at the authenticated, branch-checked SecureMediaController route so patient
     * files can't be fetched without logging in. Add ?dl=1 for an audited download.
     */
    public function getOriginalUrlAttribute(): string
    {
        return route('secure.media.file', $this->getKey());
    }

    /**
     * Watermarked copy URL if it exists, otherwise null. Also served through the
     * authenticated route (the ?v=wm flag tells the controller which file to send).
     */
    public function getWatermarkedUrlAttribute(): ?string
    {
        if (!$this->watermarked_path) return null;
        return route('secure.media.file', [$this->getKey(), 'v' => 'wm']);
    }

    /**
     * Returns watermarked URL when available, falls back to original.
     * Used for thumbnail display and download.
     */
    public function getDisplayUrlAttribute(): string
    {
        return $this->watermarked_url ?? $this->original_url;
    }

    /**
     * Thumbnail URL — image files use display URL; non-images use a generic icon.
     */
    public function getThumbnailUrlAttribute(): string
    {
        if ($this->isImage()) return $this->display_url;
        return asset('images/clinical/file-placeholder.svg');
    }

    // ── Type Helpers ───────────────────────────────────────────────────────────

    public function isImage(): bool
    {
        return in_array($this->file_type, self::IMAGE_TYPES);
    }

    public function isVideo(): bool
    {
        return $this->file_type === 'video';
    }

    public function isPdf(): bool
    {
        return in_array($this->file_type, ['pdf', 'consent', 'estimate', 'invoice', 'lab_slip']);
    }

    /**
     * Human-readable label for the file type.
     */
    public function getFileTypeLabelAttribute(): string
    {
        return match($this->file_type) {
            'photo'          => 'Photo',
            'video'          => 'Video',
            'xray'           => 'X-ray',
            'opg'            => 'OPG',
            'cbct'           => 'CBCT',
            'stl'            => 'STL',
            'intraoral_scan' => 'Scan',
            'pdf'            => 'PDF',
            'consent'        => 'Consent',
            'estimate'       => 'Estimate',
            'invoice'        => 'Invoice',
            'lab_slip'       => 'Lab Slip',
            default          => 'File',
        };
    }

    /**
     * Human-readable stage label.
     */
    public function getStageLabelAttribute(): string
    {
        return match($this->stage) {
            'before'   => 'Before',
            'during'   => 'During',
            'after'    => 'After',
            'followup' => 'Follow-up',
            default    => 'General',
        };
    }

    /**
     * Human-readable file size (KB / MB).
     */
    public function getFileSizeHumanAttribute(): string
    {
        if ($this->file_size < 1024) return $this->file_size . ' B';
        if ($this->file_size < 1048576) return round($this->file_size / 1024, 1) . ' KB';
        return round($this->file_size / 1048576, 1) . ' MB';
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    /** All files for a specific patient. */
    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /** All files for a specific visit. */
    public function scopeForVisit($query, int $visitId)
    {
        return $query->where('visit_id', $visitId);
    }

    /** Eligible for the Marketing / Content Manager tab. */
    public function scopeMarketingEligible($query)
    {
        return $query->where('is_marketing_eligible', true);
    }

    /** Eligible for the Education Library tab. */
    public function scopeEducationEligible($query)
    {
        return $query->where('is_education_eligible', true);
    }

    /** Eligible for the Case Library tab. */
    public function scopeCaseLibraryEligible($query)
    {
        return $query->where('is_case_library_eligible', true);
    }

    /** Eligible for the Teaching Library tab. */
    public function scopeTeachingEligible($query)
    {
        return $query->where('is_teaching_eligible', true);
    }

    /** Eligible for the Research Library tab. */
    public function scopeResearchEligible($query)
    {
        return $query->where('is_research_eligible', true);
    }

    /** Files ready for marketing use: consent given + approved. */
    public function scopeMarketingReady($query)
    {
        return $query
            ->where('is_marketing_eligible', true)
            ->where('consent_status', 'given')
            ->where('marketing_status', 'approved');
    }
}
