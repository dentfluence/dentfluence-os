<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrStaffDocument extends Model
{
    protected $table = 'hr_staff_documents';

    protected $fillable = [
        'user_id',
        'document_type',
        'label',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'notes',
        'uploaded_by',
    ];

    /* ── Document type labels ── */
    public static array $typeLabels = [
        'contract'    => 'Employment Contract',
        'id_proof'    => 'ID Proof',
        'certificate' => 'Certificate / Degree',
        'bank'        => 'Bank Document',
        'other'       => 'Other',
    ];

    public function getTypeLabelAttribute(): string
    {
        return self::$typeLabels[$this->document_type] ?? ucfirst($this->document_type);
    }

    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size ?? 0;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    /* ── Relationships ── */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
