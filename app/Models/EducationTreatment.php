<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class EducationTreatment extends Model
{
    protected $table = 'education_treatments';

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'description',
        'thumbnail_path',
        'primary_media_type',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(EducationCategory::class, 'category_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(EducationMedia::class, 'treatment_id');
    }

    public function getThumbnailUrlAttribute(): string
    {
        if ($this->thumbnail_path) {
            return Storage::url($this->thumbnail_path);
        }
        return asset('images/cms/treatment-placeholder.jpg');
    }

    /** Count media by type */
    public function getPhotoCountAttribute(): int
    {
        return $this->media()->where('media_type', 'photo')->count();
    }

    public function getXrayCountAttribute(): int
    {
        return $this->media()->whereIn('media_type', ['xray', 'opg', 'cbct'])->count();
    }

    public function getVideoCountAttribute(): int
    {
        return $this->media()->where('media_type', 'video')->count();
    }

    public function scopeActive($q)
    {
        return $q->where('is_published', true);
    }
}
