<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ConsultationScan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'consultation_id',
        'scan_date',
        'path',
        'original_name',
        'mime_type',
        'notes',
    ];

    protected $casts = [
        'scan_date' => 'date',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    /**
     * Returns the full public URL for this scan file.
     * Usage: $scan->url
     */
    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }
}
