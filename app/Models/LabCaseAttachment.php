<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * LabCaseAttachment — file attached to a lab case.
 * Categories: stl | intraoral_photo | shade_photo | xray | prescription | pdf | other
 * Soft-deleted only (audit trail) — files stay on disk.
 */
class LabCaseAttachment extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'stl'             => 'STL File',
        'intraoral_photo' => 'Intraoral Photo',
        'shade_photo'     => 'Shade Photo',
        'xray'            => 'X-ray',
        'prescription'    => 'Prescription',
        'pdf'             => 'PDF',
        'other'           => 'Other',
    ];

    protected $fillable = [
        'lab_case_id', 'file_path', 'original_name',
        'category', 'mime_type', 'size_bytes', 'uploaded_by',
    ];

    public function labCase(): BelongsTo
    {
        return $this->belongsTo(LabCase::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Other';
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    /** "2.4 MB" style human-readable size */
    public function sizeLabel(): string
    {
        $bytes = (int) $this->size_bytes;

        return match (true) {
            $bytes >= 1048576 => round($bytes / 1048576, 1) . ' MB',
            $bytes >= 1024    => round($bytes / 1024) . ' KB',
            default           => $bytes . ' B',
        };
    }
}
