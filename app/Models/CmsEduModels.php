<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsEduCategory extends Model
{
    protected $table = 'cms_edu_categories';

    protected $fillable = ['name', 'slug', 'icon', 'color', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(CmsEduItem::class, 'category_id');
    }
}


// ── CmsEduItem ─────────────────────────────────────────────────────────────────

// (In real project this would be a separate file: app/Models/CmsEduItem.php)

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CmsEduItem extends Model
{
    protected $table = 'cms_edu_items';

    protected $fillable = [
        'category_id', 'uploaded_by', 'title', 'slug', 'description',
        'media_type', 'file_path', 'thumbnail_path', 'duration_seconds',
        'sort_order', 'is_active', 'tags',
        'photo_count', 'xray_count', 'video_count',
    ];

    protected $casts = [
        'tags'       => 'array',
        'is_active'  => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(CmsEduCategory::class, 'category_id');
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail_path ? Storage::url($this->thumbnail_path) : null;
    }
}
