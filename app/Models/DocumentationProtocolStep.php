<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phase 7G — DocumentationProtocolStep Model
 *
 * A single required file within a documentation protocol.
 * e.g. "Pre-op IOPA" → file_type=xray, stage=before, is_required=true
 *
 * Steps are linked back to ClinicalFiles via clinical_files.protocol_step_id.
 * This lets the Documents tab show "3 of 6 steps complete" for a visit.
 */
class DocumentationProtocolStep extends Model
{
    protected $table = 'documentation_protocol_steps';

    protected $fillable = [
        'protocol_id',
        'name',
        'description',
        'file_type',
        'stage',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'sort_order'  => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(DocumentationProtocol::class, 'protocol_id');
    }

    /**
     * Clinical files that were uploaded to fulfil this step.
     */
    public function clinicalFiles(): HasMany
    {
        return $this->hasMany(ClinicalFile::class, 'protocol_step_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

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
}
