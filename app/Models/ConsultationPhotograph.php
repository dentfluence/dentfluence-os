<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ConsultationPhotograph extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'consultation_id',
        'slot',
        'path',
        'original_name',
        'mime_type',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    /**
     * Returns the full public URL for this photograph.
     * Usage: $photo->url
     */
    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }
}
