<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class EducationMedia extends Model
{
    protected $table = 'education_media';

    protected $fillable = [
        'treatment_id', 'media_type', 'file_path', 'thumbnail_path',
        'title', 'description', 'duration_seconds', 'sort_order',
    ];

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(EducationTreatment::class, 'treatment_id');
    }

    public function getFileUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }

    public function getThumbnailUrlAttribute(): string
    {
        if ($this->thumbnail_path) return Storage::url($this->thumbnail_path);
        return asset('images/cms/media-placeholder.jpg');
    }

    public function getFormattedDurationAttribute(): ?string
    {
        if (!$this->duration_seconds) return null;
        $m = floor($this->duration_seconds / 60);
        $s = $this->duration_seconds % 60;
        return sprintf('%d:%02d', $m, $s);
    }
}
