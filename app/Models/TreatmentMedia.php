<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TreatmentMedia extends Model
{
    protected $fillable = [
        'treatment_id',
        'media_type',
        'label',
        'file_path',
        'external_url',
        'mime_type',
        'file_size',
        'sort_order',
        'is_active',
        'uploaded_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public const TYPE_LABELS = [
        'image'            => 'Image',
        'video'            => 'Video',
        'pdf'              => 'PDF Document',
        'consent_template' => 'Consent Template',
        'pre_care_sheet'   => 'Pre-Care Sheet',
        'post_care_sheet'  => 'Post-Care Sheet',
        'protocol_doc'     => 'Protocol Document',
    ];

    public const TYPE_ICONS = [
        'image'            => '<path d="M21 19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14z"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
        'video'            => '<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>',
        'pdf'              => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
        'consent_template' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'pre_care_sheet'   => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'post_care_sheet'  => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'protocol_doc'     => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getUrlAttribute(): ?string
    {
        if ($this->external_url) return $this->external_url;
        if ($this->file_path)   return Storage::url($this->file_path);
        return null;
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->media_type] ?? ucwords(str_replace('_', ' ', $this->media_type));
    }

    public function getIconAttribute(): string
    {
        return self::TYPE_ICONS[$this->media_type] ?? '<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/>';
    }

    public function getFileSizeHumanAttribute(): string
    {
        if (!$this->file_size) return '';
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = $this->file_size;
        while ($size >= 1024 && $i < 3) { $size /= 1024; $i++; }
        return round($size, 1) . ' ' . $units[$i];
    }
}
